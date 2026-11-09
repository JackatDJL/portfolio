"""Machine-readable integration checks, not a rendering or composition engine."""
import json
import sys
import subprocess
import xml.etree.ElementTree as ET
import hashlib
from pypdf import PdfReader

reader = PdfReader(sys.argv[1], strict=True)
page_ids = {page.indirect_reference.idnum: i for i, page in enumerate(reader.pages)}
urls, destinations, images = [], [], []
for page in reader.pages:
    targets = []
    page_images = []
    xobjects = page['/Resources'].get_object().get('/XObject')
    if xobjects:
        for reference in xobjects.get_object().values():
            image = reference.get_object()
            if image.get('/Subtype') == '/Image':
                page_images.append(hashlib.sha256(image.get_data()).hexdigest())
    for ref in page.get('/Annots', []):
        annotation = ref.get_object()
        action = annotation.get('/A', {})
        if action.get('/URI'):
            urls.append(str(action['/URI']))
        dest = annotation.get('/Dest', action.get('/D') if action.get('/S') == '/GoTo' else None)
        if isinstance(dest, str):
            dest = reader.named_destinations[dest].dest_array
        if dest:
            targets.append(page_ids[dest[0].idnum])
    destinations.append(targets)
    images.append(page_images)
bbox = ET.fromstring(subprocess.run(['pdftotext', '-f', '1', '-l', '1', '-bbox', sys.argv[1], '-'], capture_output=True, check=True).stdout)
words = [[word.text, *[float(word.attrib[key]) for key in ['xMin', 'yMin', 'xMax', 'yMax']]] for word in bbox.iter() if word.tag.endswith('}word')]
print(json.dumps({
    'first_words': words,
    'fonts': [[str(font.get_object().get('/BaseFont', '')) for font in page['/Resources'].get('/Font', {}).get_object().values()] for page in reader.pages],
    'sizes': [[float(p.mediabox.width), float(p.mediabox.height)] for p in reader.pages],
    'texts': [page.extract_text() for page in reader.pages],
    'outline': [item.title for item in reader.outline if not isinstance(item, list)],
    'urls': urls, 'destinations': destinations, 'images': images,
}))
