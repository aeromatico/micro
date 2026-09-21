Manage project documentation in /docs — audit, update, validate, or sync.

## Usage
`/docs <action> [target]`

**Actions:** `audit`, `update`, `sync`, `validate`, `stats`

**Examples:**
- `/docs audit` — Full documentation audit
- `/docs update entorno` — Update a specific doc
- `/docs update skills` — Refresh skills documentation from .claude/commands/
- `/docs sync` — Sync all docs with current code state
- `/docs validate` — Check for broken paths, outdated info, inconsistencies
- `/docs stats` — Show documentation coverage stats

---

## Instructions

### `audit` — Full documentation audit

Read all files in `docs/` then check against the actual project:

1. **Verify environment info** (`docs/entorno.md`):
   - Run `ls /www/server/php/84/bin/php` — confirm path is correct
   - Run `/www/server/php/84/bin/php --version` — confirm PHP version
   - Run `/www/server/php/84/bin/php artisan --version` — confirm Laravel version
   - Run `redis-cli --version` — confirm Redis version
   - Confirm Nginx vhost path exists

2. **Verify plugins** (`docs/estructura.md`):
   - Run `find /www/wwwroot/micro.clouds.com.bo/plugins -name "Plugin.php" | grep -v fixtures`
   - Compare with what's documented

3. **Verify skills** (`docs/skills/README.md`):
   - Run `ls /www/wwwroot/micro.clouds.com.bo/.claude/commands/`
   - Confirm all skills in README match actual files

4. **Verify theme** (`docs/estructura.md`):
   - Run `ls /www/wwwroot/micro.clouds.com.bo/themes/`
   - Confirm active theme matches `ACTIVE_THEME` in `.env`

5. **Check for stale info**:
   - Any commands that use `php artisan` instead of `/www/server/php/84/bin/php artisan`
   - Any references to paths that don't exist
   - Any version numbers that are outdated

Report: what's correct, what's outdated, what's missing.

---

### `update <target>` — Update specific doc

If target is `entorno`: update `docs/entorno.md` with current server state.
If target is `stack`: update `docs/stack.md` with current dependency versions.
If target is `estructura`: update `docs/estructura.md` with current project tree.
If target is `skills` or `commands`: regenerate `docs/skills/README.md` table from actual `.claude/commands/` files.
If target is a filename: read and update that specific doc.

Always:
1. Read the current file first
2. Run relevant bash commands to get current state
3. Update only what's changed
4. Keep the existing structure and format
5. Update "Última actualización" date to 2026-06-10

---

### `sync` — Sync all docs with code

Systematically go through each doc and bring it up to date:
1. `docs/entorno.md` — re-verify all paths, versions, commands
2. `docs/stack.md` — re-verify versions from composer.json and package.json
3. `docs/estructura.md` — re-verify directory structure
4. `docs/skills/README.md` — re-count and re-list skills
5. `docs/workflows.md` — verify all commands work

Report what changed.

---

### `validate` — Check for quality issues

Scan all docs for:
- Commands using bare `php` instead of `/www/server/php/84/bin/php`
- References to non-existent paths
- Outdated version numbers (compare with actual)
- Missing docs for skills that exist
- Skills in docs that no longer have a corresponding `.claude/commands/*.md` file
- Broken internal links (references to other docs that don't exist)

For each issue found: show the file, line context, and suggested fix.

---

### `stats` — Documentation coverage

Show:
- Total docs: X files, Y KB
- Skills documented vs total skills
- Plugins documented vs total plugins
- Last update date per file (from git log or file content)
- Missing sections: any skill without docs, any workflow without example

Run:
```bash
find /www/wwwroot/micro.clouds.com.bo/docs -name "*.md" | wc -l
find /www/wwwroot/micro.clouds.com.bo/.claude/commands -name "*.md" | wc -l
wc -l /www/wwwroot/micro.clouds.com.bo/docs/**/*.md 2>/dev/null || find /www/wwwroot/micro.clouds.com.bo/docs -name "*.md" -exec wc -l {} +
```

---

## Quality rules (always apply when modifying docs)

- Commands use `/www/server/php/84/bin/php artisan` — never bare `php artisan`
- Never document real passwords — use `ver .env` or `(ver .env)`
- Verify file paths exist before documenting them
- Keep "Última actualización: YYYY-MM-DD" in docs that have it
- Don't duplicate: if info is in one doc, reference it from others
- All code blocks must have language hints: ```bash, ```php, ```yaml, ```twig
