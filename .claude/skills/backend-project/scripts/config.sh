#!/usr/bin/env bash

# Backend Skill Configuration

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

SKILL_WORKSPACE_DIR="$(cd "$SKILL_DIR/../../.." && pwd)"

PROJECTS_DIR="$(dirname "$SKILL_WORKSPACE_DIR")"
