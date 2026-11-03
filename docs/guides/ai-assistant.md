# AI Assistant Integration

AI Content Strategy includes skill files for AI coding assistants, letting you manage recommendations through natural language prompts in your terminal.

## Supported assistants

- **Claude Code**: uses `.claude/skills/acs/SKILL.md`
- **Codex CLI, Gemini CLI, Copilot, Cursor**: uses `.agents/skills/acs/SKILL.md`

## Setup

Run the setup command from your Drupal project root:

```bash
drush acs:setup-ai
```

This copies the skill files from the module into your project root. The files teach the assistant about all 23 `acs:*` Drush commands so it can run them on your behalf.

### Targeting a specific assistant

```bash
drush acs:setup-ai --host=claude     # Claude Code only
drush acs:setup-ai --host=agents     # Codex / Gemini / Copilot / Cursor
drush acs:setup-ai                   # all (default)
```

### Checking for updates

After updating the module, check whether the installed skill files are still current:

```bash
drush acs:setup-ai --check
```

If the files are outdated, run `drush acs:setup-ai` again to update them.

## Usage

Once the skill files are installed, your AI assistant can discover and use the module's commands. Example prompts:

- "Generate content recommendations for my site"
- "Show me all high-priority content gaps"
- "Mark the idea about case studies as implemented"
- "Create a new category for seasonal content"
- "Export recommendations as CSV"
- "What is the status of my content strategy recommendations?"

The assistant translates your request into the appropriate `acs:*` Drush command, runs it, and presents the results.

## How it works

The skill files contain a structured description of every `acs:*` command, its arguments, options, and expected output format. When your assistant reads these files, it gains the knowledge to:

1. Choose the right command for your request
2. Supply the correct arguments and options
3. Interpret the YAML output the commands return
4. Chain commands together for multi-step workflows (e.g. generate, then report, then export)
