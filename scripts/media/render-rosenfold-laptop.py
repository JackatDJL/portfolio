"""Render an unbranded laptop around an unchanged, approved development capture.

This is an illustration, never evidence of physical hardware or a file transfer.
Run with Blender's Python and pass --screen and --output after --.
"""

import argparse
import math
import sys

import bpy
from mathutils import Vector

args = sys.argv[sys.argv.index("--") + 1 :]
p = argparse.ArgumentParser()
p.add_argument("--screen", required=True)
p.add_argument("--output", required=True)
a = p.parse_args(args)
bpy.ops.object.select_all(action="SELECT")
bpy.ops.object.delete(use_global=False)


def mat(name, color, metal=0, rough=0.4):
    m = bpy.data.materials.new(name)
    m.diffuse_color = (*color, 1)
    m.use_nodes = True
    n = m.node_tree.nodes.get("Principled BSDF")
    n.inputs["Base Color"].default_value = (*color, 1)
    n.inputs["Metallic"].default_value = metal
    n.inputs["Roughness"].default_value = rough
    return m


metal = mat("Graphite aluminium", (0.14, 0.17, 0.16), 0.8, 0.28)
edge = mat("Cut metal edge", (0.29, 0.32, 0.30), 0.85, 0.23)
black = mat("Black keycaps", (0.018, 0.023, 0.02), 0.1, 0.33)
rubber = mat("Display bezel", (0.007, 0.01, 0.008), 0.05, 0.25)
floor = mat("Matte studio surface", (0.72, 0.77, 0.72), 0, 0.65)


def cube(name, loc, scale, material, bevel=0.02, rotation=None):
    bpy.ops.mesh.primitive_cube_add(size=1, location=loc)
    o = bpy.context.object
    o.name = name
    o.dimensions = scale
    bpy.ops.object.transform_apply(location=False, rotation=False, scale=True)
    if rotation:
        o.rotation_euler = rotation
    o.data.materials.append(material)
    if bevel:
        mod = o.modifiers.new("Machined radius", "BEVEL")
        mod.width = bevel
        mod.segments = 5
        o.modifiers.new("Surface normals", "WEIGHTED_NORMAL")
    return o


cube("Laptop lower shell", (0, 0, 0.16), (3.35, 2.24, 0.13), metal, 0.065)
cube("Upper deck", (0, 0, 0.225), (3.31, 2.20, 0.025), edge, 0.045)
for row in range(5):
    for col in range(14):
        cube(
            "Key",
            (-1.39 + col * 0.214, 0.64 - row * 0.224, 0.256),
            (0.185, 0.18, 0.026),
            black,
            0.018,
        )
cube("Space key", (0, -0.46, 0.256), (1.3, 0.15, 0.026), black, 0.018)
cube("Trackpad", (0, -0.82, 0.246), (1.05, 0.43, 0.008), metal, 0.045)
for side in [-1, 1]:
    for n in range(28):
        cube(
            "Speaker perforation",
            (side * 1.57, -0.15 + n * 0.027, 0.245),
            (0.012, 0.012, 0.002),
            black,
            0.004,
        )
angle = math.radians(76)
center = Vector((0, 1.17, 1.32))
normal = Vector((0, -math.sin(angle), math.cos(angle)))
cube("Display housing", center, (3.38, 2.18, 0.085), metal, 0.06, (angle, 0, 0))
cube(
    "Display bezel",
    center + normal * 0.05,
    (3.29, 2.09, 0.012),
    rubber,
    0.04,
    (angle, 0, 0),
)
bpy.ops.mesh.primitive_plane_add(
    size=1, location=center + normal * 0.059, rotation=(angle, 0, 0)
)
screen = bpy.context.object
screen.name = "Approved development capture"
screen.scale = (3.14, 1.9625, 1)
m = bpy.data.materials.new("Screenshot, unchanged")
m.use_nodes = True
nodes = m.node_tree.nodes
bsdf = nodes.get("Principled BSDF")
tex = nodes.new("ShaderNodeTexImage")
tex.image = bpy.data.images.load(a.screen)
# Preserve the capture's proportions on the 16:10 panel, including CAD canvases.
image_ratio = tex.image.size[0] / tex.image.size[1]
panel_ratio = 3.14 / 1.9625
scale_u = max(1, panel_ratio / image_ratio)
scale_v = max(1, image_ratio / panel_ratio)
coordinates = nodes.new("ShaderNodeTexCoord")
mapping = nodes.new("ShaderNodeMapping")
mapping.inputs["Scale"].default_value = (scale_u, scale_v, 1)
mapping.inputs["Location"].default_value = ((1 - scale_u) / 2, (1 - scale_v) / 2, 0)
m.node_tree.links.new(coordinates.outputs["UV"], mapping.inputs["Vector"])
m.node_tree.links.new(mapping.outputs["Vector"], tex.inputs["Vector"])
tex.extension = "CLIP"
# A CAD canvas may be transparent over the viewer's white page background.
# Composite only that alpha; the captured geometry and interface remain intact.
background = nodes.new("ShaderNodeMixRGB")
background.inputs[1].default_value = (1, 1, 1, 1)
m.node_tree.links.new(tex.outputs["Alpha"], background.inputs[0])
m.node_tree.links.new(tex.outputs["Color"], background.inputs[2])
m.node_tree.links.new(background.outputs[0], bsdf.inputs["Base Color"])
m.node_tree.links.new(background.outputs[0], bsdf.inputs["Emission Color"])
bsdf.inputs["Emission Strength"].default_value = 0.55
bsdf.inputs["Roughness"].default_value = 0.28
bsdf.inputs["Specular IOR Level"].default_value = 0.12
screen.data.materials.append(m)
cube("Studio table", (0, 0, -0.04), (200, 200, 0.2), floor, 0.02)


def area(name, loc, power, size, color):
    bpy.ops.object.light_add(type="AREA", location=loc)
    o = bpy.context.object
    o.name = name
    o.data.energy = power
    o.data.shape = "DISK"
    o.data.size = size
    o.data.color = color
    o.rotation_euler = (
        (Vector((0, 0, 0.5)) - o.location).to_track_quat("-Z", "Y").to_euler()
    )


area("Large window", (-3, -4, 6), 650, 5, (1, 0.96, 0.89))
area("Soft fill", (4, 1, 4), 500, 4, (0.87, 0.94, 1))
area("Rim", (-1, 4, 4), 400, 3, (1, 1, 1))
bpy.ops.object.camera_add(location=(1.5, -5.3, 2.7))
camera = bpy.context.object
camera.rotation_euler = (
    (Vector((0, 0.25, 1)) - camera.location).to_track_quat("-Z", "Y").to_euler()
)
camera.data.lens = 48
camera.data.dof.use_dof = True
camera.data.dof.focus_distance = (camera.location - Vector((0, 0.25, 1))).length
camera.data.dof.aperture_fstop = 7
scene = bpy.context.scene
scene.camera = camera
scene.render.engine = "CYCLES"
scene.cycles.samples = 64
scene.cycles.use_denoising = True
scene.render.resolution_x = 1800
scene.render.resolution_y = 1350
scene.render.resolution_percentage = 100
scene.world.color = (0.25, 0.25, 0.25)
scene.view_settings.view_transform = "AgX"
scene.render.image_settings.file_format = "PNG"
scene.render.filepath = a.output
bpy.ops.render.render(write_still=True)
