# Welcome to our contributing guide

Thank you for investing your time in contributing to our project! It is much appreciated.

## Asking questions & report problems

If you'd like to ask a question or report a problem, please follow these steps:

1. Look through the [existing issues](https://github.com/netresearch/t3x-rte_ckeditor_image/issues?q=is%3Aissue). Maybe we are already working on it.
2. Have a look at the [README](README.md). It might answer your question.
3. Couldn't find anything? Then feel free to create an issue. If possible, use the issue templates so we get all the necessary information.

## How to contribute code

If you want to contribute code, please follow these steps:

1. Clone the repository and checkout a local working branch.
2. Make your changes and commit them with DCO sign-off:
   ```bash
   git commit -s -m "feat: your change description"
   ```
   The `-s` flag adds a `Signed-off-by` line certifying you have the right to submit the code under the project's license ([Developer Certificate of Origin](https://developercertificate.org/)).
3. Push your working branch to the Github repository.
4. Create a pull request (PR) for your branch on Github.
5. Create an issue and [link it to your pull request](https://docs.github.com/en/issues/tracking-your-work-with-issues/linking-a-pull-request-to-an-issue).

We look through the issues and pull requests regularly.

## Project Access & Roles

The following teams have access to sensitive project resources:

| Team | Access Level | Scope |
|------|-------------|-------|
| [@netresearch/typo3](https://github.com/orgs/netresearch/teams/typo3) | Write | Source code, CI/CD workflows, issue management |
| [@netresearch/sec](https://github.com/orgs/netresearch/teams/sec) | Security | Security advisories, vulnerability reports, SECURITY.md |
| Repository Admins | Admin | Branch protection, secrets, team membership, releases |

**Secrets** (managed via GitHub Encrypted Secrets, admin-only):
- `CODECOV_TOKEN` — Code coverage reporting
- `TYPO3_TER_ACCESS_TOKEN` — TYPO3 Extension Repository publishing

### Permission escalation

Before granting elevated permissions to a contributor:

1. The contributor must have a history of quality contributions (reviewed PRs, issue reports)
2. An existing team member must sponsor the request
3. At least one repository admin must approve the access change
4. The change is logged in GitHub's organization audit log

## Help translate this extension

You can help translate this extension into your language through TYPO3's Crowdin platform:

**Translation Platform**: https://crowdin.com/project/typo3-extension-rte_ckeditor_image

**How to contribute translations**:

1. **Create a Crowdin account** (free for open source contributors)
2. **Join the TYPO3 translation team** for your language
3. **Translate strings** directly in the Crowdin web interface
4. **Review translations** from other contributors
5. **Suggest improvements** to existing translations

**Why translate?**

- Make TYPO3 more accessible to speakers of your language
- Help the global TYPO3 community
- No programming knowledge required
- Translations are automatically integrated via pull requests

**Translation notes**:

- Some terms like "Retina", "Ultra", "Standard" are multilingual - keep as-is or transliterate if more natural in your language
- Context notes are provided for technical terms to help with accurate translation
- Your contributions are reviewed by language coordinators before integration

**Need help?**

- Contact the TYPO3 localization team on [Slack](https://typo3.slack.com/) in `#typo3-localization-team`
- Check the [TYPO3 translation documentation](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Localization/Index.html)

Again, thank you very much for taking the time to help!
