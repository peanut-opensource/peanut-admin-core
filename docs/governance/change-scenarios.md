# Documentation change scenarios

Document ID: `core-doc-governance-change-scenarios`

`./scripts/core-docs-governance scenarios` validates all eight rows.

| Scenario | Upstream | Technical docs | Developer projection | Explicit non-target | Acceptance |
| --- | --- | --- | --- | --- | --- |
| Internal refactor | implementation | none with reason | none | API, guides, navigation | only `none` |
| HTTP API | OpenAPI + route/handler | API + coverage ledger | API/Module examples | unrelated packages | technical + site + generated |
| CLI | executable + help | named install/test guide | named task guide | architecture | technical + site |
| Schema/data owner | KernelSchema/manifest | schema + architecture | Module/upgrade | unrelated UI | decision + technical + site |
| Module manifest | manifest + schema | architecture + Module guide | Module tutorial | other Modules and unrelated generated files | technical + site; generated is excluded until a checked-in consumer exists |
| Environment/config | Compose/profile/help | install/upgrade | install/troubleshooting | API/status | technical + site |
| Runtime status | coverage/fixed evidence | status index | none | developer guide | technical, no site |
| Core/Application boundary | decision + manifests | architecture + source map | concepts/Module guide | evidence history | decision + technical + site |
