"""Add a named-destination bookmark to a synthetic PDF attachment."""
import sys

from pypdf import PdfReader, PdfWriter
from pypdf.generic import NameObject, TextStringObject

source_path, output_path, title, page_number = sys.argv[1:]
writer = PdfWriter()
writer.append(PdfReader(source_path, strict=True))
writer.add_named_destination("shared-outline-destination", int(page_number))
outline = writer.add_outline_item(title, int(page_number))
outline.get_object()[NameObject("/Dest")] = TextStringObject("shared-outline-destination")
with open(output_path, "wb") as output:
    writer.write(output)
