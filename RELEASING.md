# Releasing

## CRITICAL: release ordering

**The core package `codysseydev/argus` MUST be tagged and available on Packagist
BEFORE tagging this package.**

This package's `composer.json` declares `"codysseydev/argus": "^1.0"`. If the core
is not yet on Packagist, `composer install` will fail for anyone installing
`codysseydev/argus-api`, including CI. Tag and publish the core first, confirm the
Packagist listing is live, then proceed here.

See also the org-wide release playbook in `codysseydev/.github`.

---

## Checklist

1. **Core is published first.** Confirm `https://packagist.org/packages/codysseydev/argus`
   shows the target version before continuing.

2. **Update CHANGELOG.md.** Move the `[Unreleased]` entries to a new dated section:
   ```
   ## [X.Y.Z] - YYYY-MM-DD
   ```
   Update the comparison links at the bottom of the file.

3. **Run tests.**
   ```bash
   composer test
   ```
   All tests must pass. Redis must be running locally.

4. **Run Pint.**
   ```bash
   vendor/bin/pint --test
   ```
   No diffs allowed.

5. **Commit.**
   ```bash
   git add CHANGELOG.md
   git commit -m "chore: prepare release vX.Y.Z"
   ```

6. **Tag.**
   ```bash
   git tag vX.Y.Z
   git push origin main --tags
   ```

7. **Verify GitHub Release.** GitHub Actions (`release.yml`) creates the release
   automatically from the tag with generated release notes. Check the
   [Releases page](https://github.com/codysseydev/argus-api/releases).

8. **Verify Packagist.** Packagist auto-updates via the GitHub webhook. Confirm the
   new version appears at
   [packagist.org/packages/codysseydev/argus-api](https://packagist.org/packages/codysseydev/argus-api)
   within a few minutes.
