# Arc Lint
[Arc Lint](https://secure.phabricator.com/book/phabricator/article/arcanist_lint/) is used in Freelancer to provide a consistent linting experience across different repositories.

## Usage
### Config
Navigate to the project root directory and update `.arclint` to update lint rules.

### Run
Navigate to the project root directory and run:
```sh
arc lint
```

## Development
### Adding a New Linter
Linters act as adapters between the linting engine and the actual linting tool. They invoke the linting tool and parse the results into a format that Arcanist can understand. To add a new linter, follow these steps:

1. **Create a New Linter Class**:
  - Add a class that extends `ArcanistLinter` in the `src/lint/linter` directory.

2. **Add Unit Tests**:
  - Write unit tests for the new linter in the `src/lint/__tests__/` directory.

3. **Generate Library Map**:
  - Run `arc liberate` to generate the `src/__phutil_library_map__.php` file.

4. **Bump `flarc` version (recommended)**:
  - Update the `flarc` version a new version. see [README.md](../../README.md).
