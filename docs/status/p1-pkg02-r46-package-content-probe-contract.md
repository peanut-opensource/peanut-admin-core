# P1-PKG02-R46 Package Content Probe Contract

## Observed Probe Failure

The first package-content command stopped before Composer validation because an
extra local filename probe rejected every path containing `credential`. Its
read-only diagnosis listed only the credential table migration, credential
domain classes, and a credential security test. No environment file, private
key, certificate bundle, credential payload, or generated secret was found.

This was a probe-definition failure before the contract's required Composer or
npm inspection ran. It is not evidence that candidate
`b84b8876cf24e7b749f0e79ab95053e772c922e7` contains secret material, and no
package source changed.

## Authorized Retry

R46 authorizes one complete package-content inspection of the unchanged fixed
candidate. Secret-material checks must reject only concrete artifact forms:

- `.env` and `.env.*` files;
- `*.pem`, `*.key`, `*.p12`, and `*.pfx` files;
- `id_rsa`, `id_ed25519`, `credentials.json`, and service-account JSON files.

Source files, migrations, and tests whose domain names contain `Credential`
must not be treated as secrets solely because of that term.

The inspection must still validate the exact Composer metadata and ten runtime
namespace roots, run strict Composer validation, inspect the npm dry-run file
list and all declared exports, reject Host/application/test-fixture/secret
content where forbidden, and calculate SHA-256 for both immutable projections.

R46 changes only this contract. It must not alter the candidate, package,
manifest, lock, Runtime, test, script, version, or prior evidence. A required
inspection failure receives one read-only diagnosis and stops; a pass permits
the qualification evidence write set already defined by P1-PKG02.
