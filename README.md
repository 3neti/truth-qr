# Truth QR

`3neti/truth-qr` provides a plain PHP core for compact, offline-verifiable QR records. Its first domain driver implements the AES ballot and election-return wire formats. Laravel applications may use the optional service provider; the core does not require Laravel.

## End-to-end method

1. A versioned domain profile identifies record types, compact codes, reference mappings, and verification rules. The election profile is declared in `profiles/election.yaml`; its behavior is implemented by tested PHP classes.
2. A domain driver validates a record, maps its facts to compact codes, and serializes deterministic canonical bytes. Mapping and document references bind those bytes to locally commissioned materials.
3. A transport codec makes the bytes safe for QR carriage. A versioned envelope carries the record type and divides a large record into numbered fragments bound by a common SHA-256 payload hash.
4. A writer renders each envelope as a QR symbol on a human-readable paper artifact. Rendering size and image format do not alter the encoded record.
5. An offline verifier scans the fragments in any order, rejects mixed, conflicting, incomplete, or corrupted sets, reassembles the bytes, and checks the active mapping, election context, and required signature against local reference materials.
6. The domain driver reconstructs the record for human review. A host application can append verification events to its audit trail.

This is an engineering method description, not a determination of patentability.

## AES wire compatibility

The election driver preserves `truth://v1/waes-ballot/aes-ballot-compact-1` and `truth://v1/waes-election-return/waes-er-compact-1`, including `waes-er-fragment-1` sets. It also reads legacy `vaes-ballot` envelopes. The older `lbhurtado/truth-qr-php` `truth://v1/ER/...` format is a different protocol and is not accepted by this profile.

The core separates serialization, transport, envelope parsing, image writing, and image decoding. Host applications provide candidate mappings, document references, precinct context, and trusted signing keys through contracts. AES remains the first supported application; later drivers can serve surveys or field data without changing the core.
