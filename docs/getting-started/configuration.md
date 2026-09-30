# Configuration

## Permissions

The module defines two permissions at `/admin/people/permissions`:

| Permission | Purpose |
|---|---|
| **Access AI content strategy recommendations** | View and interact with the recommendations dashboard |
| **Administer AI content strategy** | Manage categories, edit settings, and configure the system prompt |

Grant the access permission to content editors and marketing team members. Reserve the administer permission for site builders.

## Settings

Visit `/admin/config/ai/content-strategy/settings` to configure:

### System prompt

The system prompt sets the AI's role and behaviour for all recommendation categories. It is sent as a system message before the category-specific instructions.

The default prompt instructs the AI to:

- Base recommendations on the site's actual content and structure
- Keep suggestions specific to the site's domain (never generic)
- Make recommendations actionable and practical
- Focus on improving user value and engagement

Edit the system prompt to adjust the AI's tone, focus area, or analysis approach. For example, you might instruct it to prioritise SEO considerations or to write in a particular voice.

## Categories

Categories control which types of recommendations the module generates. Visit `/admin/config/ai/content-strategy/categories` to manage them.

The module ships with seven built-in categories:

1. **Content Gaps**: missing content that visitors would expect
2. **Authority Topics**: subjects where the site could establish thought leadership
3. **Content Series**: opportunities for multi-part content sequences
4. **Expertise Demonstrations**: ways to showcase domain knowledge
5. **Orphaned Topics**: existing content that lacks supporting pages
6. **Trust Signals**: content that builds credibility (case studies, testimonials, certifications)
7. **Underutilised Types**: content formats the site could explore (video, infographics, interactive tools)

Built-in categories cannot be deleted but can be disabled or edited. See the [categories guide](../guides/categories.md) for details on creating custom categories.
