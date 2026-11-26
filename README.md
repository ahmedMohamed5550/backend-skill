# backend-skill

Company-approved Laravel backend standards packaged as a Claude Code skill (`.claude/skills/backend-skill/`).

## Purpose

The Backend Skill is the source of truth for creating standardized Laravel backend projects: approved architecture, project structure, dependencies, templates, configuration, and development standards.

It is loaded through the project `CLAUDE.md`, which instructs Claude to read `.claude/skills/backend-skill/SKILL.md` and to follow all references linked from it.

## Layout

| Path | Contents |
|---|---|
| `CLAUDE.md` | Project instructions directing Claude to the Backend Skill. |
| `.claude/skills/backend-skill/SKILL.md` | Main skill: role, project-creation workflow, and the approved standards summary. |
| `.claude/skills/backend-skill/references/` | Mandatory per-domain standards: architecture, database, models, API standards, API documentation, authentication, localization, helpers and traits, dependencies, Swagger, testing, file storage. |
| `.claude/skills/backend-skill/scripts/` | `config.sh` (configured workspace paths) and `scaffold.sh` (standardized project scaffold). |
| `.claude/skills/backend-skill/templates/` | Approved project templates that the scaffold copies, or that are adapted when implementing modules. |

## Changing standards

Standards documented in the skill must not be changed without the user's explicit approval. `SKILL.md` is the entry point; the references it links define the details.
