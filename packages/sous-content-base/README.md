# Sous Content Base

`fourkitchens/sous-content-base` · `drupal-recipe` · GPL-2.0-or-later

Shared Page and Frontpage fields, authoring tabs, teasers and title-based
Pathauto aliases. It applies Formats and Media; Page also has tags. Title sits
above the horizontal Content/Teaser tabs, with no SEO Title field or Info tab.
The base attaches neither Body nor a Paragraph reference.

Choose [Content Types](../sous-content-types) for Body,
[Layout Paragraphs](../sous-paragraphs) for component-only Content tabs, or
[Core Layout Builder](../sous-core-layout-builder) for native layouts and four
matching inline block types. Projects can use the base directly when defining
their own composition.

The shared Editorial workflow starts content in Draft and supports Published
and Archived states, with optional scheduled publishing and unpublishing.

Recipes are applied once and then unpacked; sites own the resulting dependencies
and complete configuration export. Package updates do not migrate existing
content. See the [collection guide](../../README.md) for repository setup and the
[contributor guide](../../CONTRIBUTING.md) for source changes.

[GPL-2.0-or-later](LICENSE); dependency and package notices remain applicable.
