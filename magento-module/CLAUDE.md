@AGENTS.md

## Claude Code only

- The deny rules in `.claude/settings.json` enforce part of the no-go list
  above. If a tool call is denied, do not try another route to the same file
  or command. Stop and say what you needed and why.
- Keep this file to Claude-specific notes. A rule every agent needs belongs in
  `AGENTS.md`.
