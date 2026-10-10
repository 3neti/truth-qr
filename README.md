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

## Offline signing preflight

Truth QR supplies versioned record serialization, envelopes, fragment reassembly, and the `SignatureProvider` contract. A host application supplies signing keys, independently trusted public keys, local domain context, and the policy for accepting records. No live network or public certificate authority is required during scanning.

Before using signatures in an offline deployment, the host should:

1. Provision protected private signing keys only on authorized record-producing devices. Distribute the authentic public trust anchor and a signed, versioned roster of authorized record keys to verifiers through a controlled offline process. A public key embedded in a QR is not trusted merely because it accompanies a valid mathematical signature.
2. Bind each key to its permitted domain, record type, election or other deployment context, and validity status. Make unknown, expired, or revoked keys fail verification, and define an offline update process for key replacement.
3. Freeze the canonical record before signing. Verify the complete signed bytes against the trusted key and local profile; then apply domain checks such as precinct, mapping, duplicate, and supersession rules. Compare human-readable print with the verified record where paper is the audit artifact.
4. Test offline scans of a complete record, missing and mixed fragments, altered bytes, an untrusted signer, and an out-of-context record. Preserve the trust-roster version and verification outcome with the host audit evidence.

For a multipart record, Truth QR signs the complete canonical material once, includes that signature in the compact payload, and only then splits the encoded payload across QR symbols. Each fragment carries its index, total count, and the SHA-256 hash of the whole canonical payload. Signature bytes may span fragments. A fragment alone is not a signed record; verification waits for a complete, hash-consistent reassembly. The fragment hash binds parts to one byte sequence, while the signature authenticates that sequence against the host's trusted key.

In `v0.1.0`, the AES election-return driver uses the host-provided `SignatureProvider` in this way. The ballot format remains unsigned. Signing ballots or using an authority-signed public-key roster requires an explicit new wire version and host commissioning policy; neither is implied by the current release.
