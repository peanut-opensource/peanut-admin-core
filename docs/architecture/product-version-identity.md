# Product and Instance version identity

Document ID: `core-doc-product-version-identity`

Status: `current` — accepted product policy; a new coordinated release is not yet published.

Peanut Admin Application, Core PHP/Core Web and the Standalone/Multi-tenant Editions share one product version in future coordinated releases, including prerelease suffixes. Each repository, package and Edition retains its own immutable source and artifact identity. A matching number does not replace qualification.

Modules use independent versions and declare product compatibility. Customer Instances use independent `instance_version` values and separately record `source_product_version`, Edition and immutable source release. An instance-only business change does not increment Core or the upstream product version.

The owning decision is `repo://peanut-admin/docs/architecture/product-version-identity-adr.md`. Core publishes its package identities and qualification evidence; Application owns product capability and deployment facts. The product's next coordinated target is 3.1.0, subject to fresh qualification and publication. This page does not make that version installable.

## Historical Alpha.13

Core [v0.1.0-alpha.13](https://github.com/peanut-opensource/peanut-admin-core/releases/tag/v0.1.0-alpha.13) was published under the former independent Core sequence. Application v3.0.14 consumes that exact historical version. Existing tags, Release assets, package versions and qualification records must remain unchanged; Alpha.13 must not be relabeled as Core v3.0.14.

The [Alpha.13 qualification record](../releases/qualifications/0.1.0-alpha.13.json) identifies the candidate and evidence. Its historical success does not qualify a new package version. New package manifests, locks, projection digests, legal artifacts and clean-consumer verification must agree before a coordinated product release can consume them.

The accepted ThinkPHP Runtime direction is independent of this numbering correction: Alpha.13 still contains PDO contracts. A future Runtime migration must receive its own bounded implementation and qualification; renumbering does not implement it or start a separate Core 0.2.x product sequence.
