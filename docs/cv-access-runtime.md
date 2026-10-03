# CV access links and runtime requirements

Temporary and permanent CV capabilities live in the `cv_access_tokens` database table. Token exchange stores a token hash in the Laravel session. Private contact data and private PDFs both check that session against the active, unexpired, unrevoked database record.

The capability flow needs a Laravel application, a persistent database, and a persistent session store. Apply the database migrations when deploying. Keep `APP_KEY` stable so the database can decrypt the stored capability token when the Control Panel or a private PDF needs to build a fragment link.

Static HTML export can publish the public CV pages. It cannot issue, exchange, expire, or revoke capabilities, reveal private contact details, or render PDFs. No stateless fallback is provided. The Control Panel access widget reports when the runtime endpoints are unavailable.

PDF requests never accept `cv` or `token` query parameters. Private capabilities stay in `#cv=...` fragments, and the token is decrypted only while generating the corresponding private PDF or Control Panel link.
