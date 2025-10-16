/**
 * Commitlint configuration for conventional commits
 *
 * Following the convention:
 * <type>(<scope>): <subject>
 *
 * Types: feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert
 * Examples:
 *   feat: add new image scaling feature
 *   fix(controller): correct file validation logic
 *   docs: update README with new configuration options
 *   chore: upgrade dependencies
 */

module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [
      2,
      'always',
      [
        'feat',     // New feature
        'fix',      // Bug fix
        'docs',     // Documentation only changes
        'style',    // Changes that don't affect code meaning (formatting, etc)
        'refactor', // Code change that neither fixes a bug nor adds a feature
        'perf',     // Performance improvement
        'test',     // Adding missing tests or correcting existing tests
        'build',    // Changes that affect the build system or external dependencies
        'ci',       // Changes to CI configuration files and scripts
        'chore',    // Other changes that don't modify src or test files
        'revert',   // Reverts a previous commit
      ],
    ],
    'subject-case': [2, 'never', ['upper-case']],
    'subject-empty': [2, 'never'],
    'type-case': [2, 'always', 'lower-case'],
    'type-empty': [2, 'never'],
  },
};
