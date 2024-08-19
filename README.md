# flarc

`flarc` is a [libphutil library](https://secure.phabricator.com/book/phabcontrib/article/adding_new_classes/#creating-libraries) that contains various [Arcanist](https://secure.phabricator.com/book/phabricator/article/arcanist/) extensions used at Freelancer.com, mostly relating to [linting](https://secure.phabricator.com/book/phabricator/article/arcanist_extending_lint/) and testing integrations.

## Installation
To use `flarc`, you must clone the repository into a location that Arcanist knows how to load it. Arcanist adjusts the PHP include path in `arcanist_adjust_php_include_path` so that libphutil libraries can be loaded from any of the following locations:

  1. A directory adjacent to `arcanist/` itself.
  2. Anywhere in the normal PHP [`include_path`](https://www.php.net/manual/en/ini.core.php#ini.include-path).
  3. Inside `arcanist/externals/includes/`.

## Usage
To use `flarc` in your project, add `flarc/src` to the `load` path in the project's `.arcconfig`:

```json
{
  "load": ["flarc/src"]
}
```

### Usage in `fl-gaf`
To pin the version of `flarc` used in `fl-gaf`, update the `FLARC_VERSION` in `rGaf/support/flarc/src/common/FlarcVersionChecker.php`.

## Development
### Adding/Updating Linters
For more information on adding or updating linters, see [lint/README.md](src/lint/README.md).

### Versioning
To create a new version of `flarc`:
- Update `composer.json` to the new version.
- Run `arc liberate` and `composer update --lock`.
