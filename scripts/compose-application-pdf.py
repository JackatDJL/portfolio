#!/usr/bin/env python3
"""Compose private PDFs without rasterizing. Input/output paths come only from PHP."""
import json
import logging
import os
import resource
import sys

from pypdf import PdfReader, PdfWriter
from pypdf.generic import ArrayObject, NameObject, TextStringObject

logging.disable(logging.CRITICAL)  # Parser warnings can contain private document data.


def normalize_outline_destinations(reader):
    """Resolve named GoTo targets before importing each attachment's outline."""
    root = reader.trailer["/Root"].get("/Outlines")
    if root is None:
        return

    page_ids = {page.indirect_reference.idnum: index for index, page in enumerate(reader.pages)}

    def walk(first):
        while first is not None:
            item = first.get_object()
            action = item.get("/A")
            if hasattr(action, "get_object"):
                action = action.get_object()
            destination = action.get("/D") if action and action.get("/S") == "/GoTo" else item.get("/Dest")
            if isinstance(destination, (str, bytes)):
                named = reader.named_destinations.get(str(destination))
                if named is None:
                    raise ValueError("Unknown outline destination")
                target = named[0]
                target_index = page_ids.get(getattr(target, "idnum", None))
                if target_index is None:
                    raise ValueError("Invalid outline destination")
                explicit = ArrayObject([reader.pages[target_index].indirect_reference, *list(named[1:])])
                if action and action.get("/S") == "/GoTo":
                    action[NameObject("/D")] = explicit
                else:
                    item[NameObject("/Dest")] = explicit
            child = item.get("/First")
            if child is not None:
                walk(child)
            first = item.get("/Next")

    walk(root.get_object().get("/First"))


def compose(manifest_path, output):
    resource.setrlimit(resource.RLIMIT_AS, (768 * 1024 * 1024, 768 * 1024 * 1024))
    with open(manifest_path, encoding="utf-8") as source:
        manifest = json.load(source)
    readers = [PdfReader(part["path"], strict=True) for part in manifest["parts"]]
    if any(reader.is_encrypted or not len(reader.pages) for reader in readers):
        raise ValueError("Encrypted or empty input")
    lengths = [len(reader.pages) for reader in readers]
    if sum(lengths) > manifest["max_pages"]:
        raise ValueError("Page limit")
    offsets = [sum(lengths[:i]) for i in range(len(lengths))]
    special = {"application-cv": offsets[1]}
    special.update({f"application-document-{i - 1}": offsets[i] for i in range(2, len(readers))})
    writer = PdfWriter()
    repairs = {}
    # Normalize named destinations to explicit source page references before import.
    # This also prevents equal destination names in different attachments colliding.
    for part_index, reader in enumerate(readers):
        normalize_outline_destinations(reader)
        page_ids = {page.indirect_reference.idnum: i for i, page in enumerate(reader.pages)}
        for page_index, page in enumerate(reader.pages):
            for annotation_index, ref in enumerate(page.get("/Annots", [])):
                annotation = ref.get_object()
                if annotation.get("/Subtype") != "/Link":
                    continue
                action = annotation.get("/A", {})
                if hasattr(action, "get_object"):
                    action = action.get_object()
                if isinstance(action.get("/URI"), str):
                    action[NameObject("/URI")] = TextStringObject(action["/URI"].strip())
                destination = annotation.get("/Dest")
                if destination is None and action.get("/S") == "/GoTo":
                    destination = action.get("/D")
                if destination is None:
                    continue
                if hasattr(destination, "get_object"):
                    destination = destination.get_object()
                name = str(destination) if isinstance(destination, (str, bytes)) else None
                if part_index == 0 and name in special:
                    target, tail = special[name], [NameObject("/Fit")]
                else:
                    if name is not None:
                        if name not in reader.named_destinations:
                            raise ValueError("Unresolved destination")
                        destination = reader.named_destinations[name].dest_array
                    if not isinstance(destination, (list, ArrayObject)) or not destination:
                        raise ValueError("Invalid destination")
                    target_ref = destination[0]
                    source_page = page_ids.get(getattr(target_ref, "idnum", None))
                    if source_page is None and isinstance(target_ref, int):
                        source_page = target_ref
                    if source_page is None or not 0 <= source_page < lengths[part_index]:
                        raise ValueError("Missing destination page")
                    target, tail = offsets[part_index] + source_page, list(destination[1:])
                marker = f"application-merge-{part_index}-{page_index}-{annotation_index}"
                repairs[marker] = (target, tail, annotation.get("/NM"))
                annotation[NameObject("/NM")] = TextStringObject(marker)
                # Use a valid local destination so append retains the link annotation.
                local_page = 0 if part_index == 0 and name in special else source_page
                local_destination = ArrayObject([reader.pages[local_page].indirect_reference, *tail])
                if "/Dest" in annotation:
                    annotation[NameObject("/Dest")] = local_destination
                else:
                    action[NameObject("/D")] = local_destination
        reader.add_form_topname(f"part{part_index}")
        writer.append(reader, outline_item=manifest["parts"][part_index]["label"], import_outline=True)
    repaired = set()
    for page in writer.pages:
        for ref in page.get("/Annots", []):
            annotation = ref.get_object()
            marker = annotation.get("/NM")
            if marker not in repairs:
                continue
            target, tail, original_name = repairs[marker]
            destination = ArrayObject([writer.pages[target].indirect_reference, *tail])
            if "/Dest" in annotation:
                annotation[NameObject("/Dest")] = destination
            else:
                annotation["/A"][NameObject("/D")] = destination
            if original_name is None:
                del annotation["/NM"]
            else:
                annotation[NameObject("/NM")] = original_name
            repaired.add(marker)
    if len(repaired) != len(repairs):
        raise ValueError("Lost internal link")
    writer.add_metadata({"/Title": "Bewerbung", "/Producer": "Portfolio application export"})
    with open(output, "xb") as target:
        writer.write(target)
    os.chmod(output, 0o600)
    # Reopen before publication: malformed output must never replace a canonical export.
    if len(PdfReader(output, strict=True).pages) != sum(lengths):
        raise ValueError("Page count changed")


if __name__ == "__main__":
    try:
        if sys.argv[1:] == ["--version"]:
            import pypdf
            print(pypdf.__version__)
        elif len(sys.argv) == 3 and sys.argv[1] == "--validate":
            reader = PdfReader(sys.argv[2], strict=True)
            if reader.is_encrypted or not len(reader.pages):
                raise ValueError("Invalid output")
        else:
            compose(sys.argv[1], sys.argv[2])
    except Exception:
        # No exception messages, document text, filenames or source tracebacks on stderr.
        sys.stderr.write("PDF composition failed.\n")
        sys.exit(1)
