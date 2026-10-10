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

## Typed record service (v0.3.0)

`RecordPayload` is an immutable, domain-agnostic input with a profile ID, an open string record type, and keyed JSON-compatible fields. The selected `RecordDriver` validates domain fields. `RecordService` defines the typed encode and decode methods; `TruthQr` is the default implementation. Laravel binds the interface to the same `TruthQr` singleton that holds registered drivers, and a host may replace that binding.

```php
use LBHurtado\TruthQr\Contracts\RecordService;
use LBHurtado\TruthQr\Core\RecordPayload;

$service = app(RecordService::class);
$scans = $service->encodeRecord(new RecordPayload('aes-election-v1', 'ballot', $ballotFields));
$decoded = $service->decodeRecord('aes-election-v1', $scans);

$decoded->recordType;       // ballot
$decoded->fields;           // verified, domain-decoded record
$decoded->canonicalPayload; // reassembled canonical bytes
```

The existing `TruthQr::encode($profileId, $recordType, $fields)` and `decode($profileId, $scans)` methods remain available. The DTO changes no QR wire bytes. Print-time labels and timestamps belong to the host's print context unless a domain profile explicitly puts them in its signed record.

## Offline signing preflight

Truth QR supplies versioned record serialization, envelopes, fragment reassembly, and the `SignatureProvider` contract. A host application supplies signing keys, independently trusted public keys, local domain context, and the policy for accepting records. No live network or public certificate authority is required during scanning.

Before using signatures in an offline deployment, the host should:

1. Provision protected private signing keys only on authorized record-producing devices. Distribute the authentic public trust anchor and a signed, versioned roster of authorized record keys to verifiers through a controlled offline process. A public key embedded in a QR is not trusted merely because it accompanies a valid mathematical signature.
2. Bind each key to its permitted domain, record type, election or other deployment context, and validity status. Make unknown, expired, or revoked keys fail verification, and define an offline update process for key replacement.
3. Freeze the canonical record before signing. Verify the complete signed bytes against the trusted key and local profile; then apply domain checks such as precinct, mapping, duplicate, and supersession rules. Compare human-readable print with the verified record where paper is the audit artifact.
4. Test offline scans of a complete record, missing and mixed fragments, altered bytes, an untrusted signer, and an out-of-context record. Preserve the trust-roster version and verification outcome with the host audit evidence.

For a multipart record, Truth QR signs the complete canonical material once, includes that signature in the compact payload, and only then splits the encoded payload across QR symbols. Each fragment carries its index, total count, and the SHA-256 hash of the whole canonical payload. Signature bytes may span fragments. A fragment alone is not a signed record; verification waits for a complete, hash-consistent reassembly. The fragment hash binds parts to one byte sequence, while the signature authenticates that sequence against the host's trusted key.

The `aes-election-v1` profile keeps its original unsigned ballot and host-signed election return formats. The opt-in `aes-election-v2` profile signs ballots with Ed25519 and retains the existing election return format. AES currently uses `v0.1.0`; adopting the new profile requires a separate AES integration and commissioning step.

## Signed ballot profile (v0.2.0)

The v2 ballot envelope is `truth://v1/waes-ballot/aes-ballot-compact-2?p=...`. Its decoded canonical payload is `aes-ballot-compact-2:AES3|election_id|precinct_id|ballot_style_id|mapping_hash|tabulation_profile|paper_ballot_serial|document_id|document_hash|asset_bundle_id|asset_bundle_hash|candidate_codes|key_id|signature`. Values use the compact ballot escaping rules; the detached signature is base64url encoded. The package signs the complete unsigned payload together with the key ID and the `election.ballot.v2` purpose. The QR carries the key ID and signature, but no public key or trust roster. It does not include an exact ballot timestamp.

The host must supply a protected Ed25519 secret key through `SigningKeyProvider::forRecord()` and resolve trusted public keys through `TrustedPublicKeyResolver::resolve()`. The resolver receives the key ID, purpose, election ID, and precinct ID so the host can enforce its offline commissioning policy. Unknown or unauthorized keys fail closed. The host owns key generation, storage, distribution, revocation, trust-roster updates, and any election-specific authorization checks. Truth QR does not derive keys from Laravel `APP_KEY` or trust keys found in scanned records.

The Laravel service provider loads both profile YAML files but registers only the v1 driver by default. After binding the host contracts and existing election adapters, register the v2 driver explicitly:

```php
use LBHurtado\TruthQr\Core\TruthQr;
use LBHurtado\TruthQr\Election\SignedElectionRecordDriver;

$truthQr = app(TruthQr::class);
$truthQr->register(app(SignedElectionRecordDriver::class));

$scans = $truthQr->encode('aes-election-v2', 'ballot', $ballot);
$verified = $truthQr->decode('aes-election-v2', $scans);
```

`decode()` throws on a bad signature, untrusted key, wrong active election or precinct, mapping mismatch, or ballot style mismatch. A successful ballot record includes `truth_signature_valid` and signature metadata. The v1 driver rejects v2 ballots, and the v2 driver rejects unsigned v1 ballots. The existing election return serializer and its `SignatureProvider` remain in use for v2 election returns; this release does not introduce a new election return wire format or an authority-signed key roster.
