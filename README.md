# flarc

`flarc` is a [libphutil library] that contains various [Arcanist][arcanist]
extensions used at Freelancer.com, mostly relating to [linting][lint] and
testing integrations.

## Usage
To use `flarc`, you must clone the repository into a location that Arcanist
knows how to load it. Arcanist adjusts the PHP include path in
`arcanist_adjust_php_include_path` so that libphutil libraries can
be loaded from any of the following locations:

  1. A directory adjacent to `arcanist/` itself.
  2. Anywhere in the normal PHP [`include_path`][include_path].
  3. Inside `arcanist/externals/includes/`.

To load `flarc` so that it is available for use, add `flarc/src` to the `load`
path:

```json
{
  "load": ["flarc/src"]
}
```

[arcanist]: https://secure.phabricator.com/book/phabricator/article/arcanist/
[include_path]: https://www.php.net/manual/en/ini.core.php#ini.include-path
[libphutil library]: https://secure.phabricator.com/book/phabcontrib/article/adding_new_classes/#creating-libraries
[lint]: https://secure.phabricator.com/book/phabricator/article/arcanist_extending_lint/
