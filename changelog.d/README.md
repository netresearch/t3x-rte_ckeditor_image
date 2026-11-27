# Changelog fragments

Each pull request writes its changelog entry into its own file here instead of
editing `## [Unreleased]` in `CHANGELOG.md`. Two pull requests that both edit
that section conflict as soon as one of them merges; two fragment files do not.

## Format

- File name: `<branch-slug>.<section>.md`, lowercase, for example
  `fix-skill-sync-lock.fixed.md`. The branch slug keeps names unique.
- `<section>` selects the heading the entry lands under:

  | Section      | Heading in `CHANGELOG.md`                         |
  |--------------|---------------------------------------------------|
  | `upgrading`  | `### Upgrading from X.Y` (the last minor release) |
  | `added`      | `### Added`                                       |
  | `changed`    | `### Changed`                                     |
  | `deprecated` | `### Deprecated`                                  |
  | `removed`    | `### Removed`                                     |
  | `fixed`      | `### Fixed`                                       |
  | `security`   | `### Security`                                    |

- Content: one or more top-level bullets (`-` and a space), written as they will appear in
  `CHANGELOG.md`. No heading; the file name selects it. Links are written inline,
  not as a `[name]: URL` definition. A change that belongs under two headings
  gets two files.

`composer ci:test:changelog` checks the names and contents. The command is
`changelog-fragments` from [netresearch/typo3-ci-workflows](https://github.com/netresearch/typo3-ci-workflows#changelog-fragments).

## Release

The release commit runs `composer changelog:collect` before it renames
`[Unreleased]`. The command appends every fragment, in file-name order, at the
end of its heading, creates a missing heading in Keep a Changelog order, and
deletes the fragment. With no fragments it changes nothing. The check fails a
release commit that leaves fragments here, because those notes would not ship.
