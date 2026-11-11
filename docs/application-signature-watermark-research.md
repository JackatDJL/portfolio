# Visual patterns and copied signature images

Research note, 11 November 2026.

## What security documents actually do

Passports use physical features that can be inspected under specific conditions. ICAO Doc 9303 describes different fluorescent colours under UV light as a way to make reproduction more complicated and to support machine checks. EU PRADO passport records show features such as fluorescent overprint that is invisible in normal light. The UK Home Office explains that UV lamps reveal reactions in secure paper and printed features; it also describes watermarks as paper-thickness changes viewed with transmitted light. These effects come from ink, substrate, lighting and inspection equipment, not from a blue image in a PDF.

- [ICAO Doc 9303, Part 2](https://www.icao.int/publications/documents/9303_p2_cons_en.pdf)
- [EU PRADO, Swedish passport, UV features](https://www.consilium.europa.eu/prado/en/SWE-AO-05001/index.html)
- [UK Home Office guidance on examining identity documents](https://www.gov.uk/government/publications/recognising-fraudulent-identity-documents/guidance-on-examining-identity-documents-accessible)

A decorative blue pattern rendered on a screen or embedded in a PDF is only a visual motif. It does not fluoresce under UV, prove that a document is genuine, or make the signature image inaccessible.

## Why transit-app ticket patterns work differently

BKK instructs riders to scan a code and show an animation from its mobile ticket flow; it also requires live mobile data for using and verifying tickets. MVV says inspectors can distinguish a genuine, active ticket from a screenshot. These are app-mediated checks with live ticket state and validation. The motion or pattern by itself is not the protection.

- [BKK mobile-ticket instructions](https://bkk.hu/en/tickets-and-passes/purchase-information/mobile-ticket/)
- [MVVswipe ticket inspection guidance](https://www.mvv-muenchen.de/en/tickets-subscriptions-prices/mvvswipe/)

Putting a similar animation over a handwritten signature would not recreate those checks. A recipient who can view the authorized PDF can still capture the page, crop the signature or extract its raster image.

## Recommendation for this system

Keep access control and private storage as the protection. Public CV pages should continue to receive no real signature data. If a decorative pattern is added later, generate it independently from the signature and describe it as decoration, not UV or anti-copy security. A recipient-specific watermark on an authorized copy could discourage casual reuse, but it can be cropped and must not be described as copy prevention or reliable attribution.

The signature remains a visual mark, not a QES. Do not mix decorative patterns or watermarks into cryptographic signature validation. External cryptographic signing must continue to run only after the final complete PDF is assembled.
