---
name: Corporate Core
colors:
  surface: '#f8f9fa'
  surface-dim: '#d9dadb'
  surface-bright: '#f8f9fa'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f4f5'
  surface-container: '#edeeef'
  surface-container-high: '#e7e8e9'
  surface-container-highest: '#e1e3e4'
  on-surface: '#191c1d'
  on-surface-variant: '#45464f'
  inverse-surface: '#2e3132'
  inverse-on-surface: '#f0f1f2'
  outline: '#767680'
  outline-variant: '#c6c5d1'
  surface-tint: '#505b92'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#09164b'
  on-primary-container: '#7680bb'
  inverse-primary: '#b9c3ff'
  secondary: '#5d5d69'
  on-secondary: '#ffffff'
  secondary-container: '#e2e1ef'
  on-secondary-container: '#63636f'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#001158'
  on-tertiary-container: '#6d7ed4'
  error: '#BA1A1A'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dee1ff'
  primary-fixed-dim: '#b9c3ff'
  on-primary-fixed: '#09164b'
  on-primary-fixed-variant: '#384379'
  secondary-fixed: '#e2e1ef'
  secondary-fixed-dim: '#c6c5d3'
  on-secondary-fixed: '#1a1b25'
  on-secondary-fixed-variant: '#454651'
  tertiary-fixed: '#dee1ff'
  tertiary-fixed-dim: '#b9c3ff'
  on-tertiary-fixed: '#001158'
  on-tertiary-fixed-variant: '#2e3f92'
  background: '#f8f9fa'
  on-background: '#191c1d'
  surface-variant: '#e1e3e4'
  on-navy: '#FFFFFF'
  card-bg: '#FFFFFF'
  surface-grey: '#F3F4F5'
  text-main: '#191C1D'
  border-base: '#E9ECEF'
  icon-grey: '#757683'
  veeba-red: '#E31E24'
  veeba-warm: '#FED563'
  woktok-orange: '#F37021'
  zyro-teal: '#008080'
  success: '#28A745'
typography:
  display:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 22px
    fontWeight: '600'
    lineHeight: 30px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 4px
  standard-gap: 24px
  margin-desktop: 40px
  margin-mobile: 16px
  max-width: 1280px
---

## Brand & Style

This design system is a high-performance **Corporate Modern** framework tailored for the VRB Learning Hub. It strikes a balance between professional discipline and brand-specific energy. The primary aesthetic is built on a "Navy-first" foundation to establish institutional trust and authority, while utilizing a "Multi-Brand Accent" strategy to provide visual cues for the Veeba, Wok Tok, and Zyro sub-brands.

The design movement is **Minimalist and Flat**, eschewing shadows and depth effects in favor of crisp borders and clear typographic hierarchy. This approach ensures high information density and reduces cognitive load for employees navigating the LMS.

**Brand marks and visual assets:**
- Use high-fidelity inline SVGs for the VRB Corporate logo and brand marks.
- Brand identity is reinforced through color-coded accents rather than complex imagery.
- Avatars use a circular initials-based system for a clean, privacy-conscious aesthetic.

## Colors

The palette is engineered for a professional, low-fatigue environment. 

- **Primary Navy:** Used for structural elements and main actions. The hover state (`#041C72`) provides a subtle, professional transition.
- **Surface Strategy:** The UI uses a three-tier surface model: Page Background (`#F8F9FA`), Secondary Surface (`#F3F4F5`), and interactive Card Backgrounds (`#FFFFFF`).
- **Brand Accents:** These colors are used for semantic brand grouping. For example, a Veeba-specific course module will utilize `#E31E24` for its progress indicators and thematic accents.
- **Typography & Borders:** High contrast is maintained using `#191C1D` for primary text. Borders use a soft grey (`#E9ECEF`) to define space without creating visual noise.

## Typography

This system utilizes **Inter** exclusively to ensure a modern, neutral, and highly legible interface across all devices.

- **Display & Headlines:** Large titles utilize tight tracking (-2%) to feel more cohesive and impactful.
- **Body Text:** Standard reading content is set to 16px to accommodate long-form learning materials comfortably.
- **Interactive Labels:** Labels use a slightly heavier weight (600) and expanded tracking (+1%) to differentiate them from static body text, ensuring buttons and navigation items are immediately recognizable.

## Layout & Spacing

The layout is built on a **12-column fixed-width grid** centered on the screen, optimized for large-format desktop viewing while maintaining fluid responsiveness for mobile.

- **Grid Strategy:** Content is contained within a `1280px` max-width container. 
- **Rhythm:** A 4px base unit governs all spacing. The standard gap between layout components is `24px`.
- **Responsive Behavior:** 
  - **Desktop:** 40px outer margins.
  - **Mobile:** 16px outer margins. Columns reflow to a single stack, and internal card padding typically reduces to 16px.

## Elevation & Depth

This design system is strictly **Flat**. Depth and hierarchy are established through color contrast and 1px borders rather than shadows or blurs.

- **Borders:** All cards, panels, and input containers use a 1px solid border in `#E9ECEF`.
- **Tonal Separation:** Functional areas are separated by background color. For example, a dashboard might use the Page BG (`#F8F9FA`) for the main workspace and White (`#FFFFFF`) for the specific content cards.
- **Interactive States:** Instead of elevation, use color shifts (e.g., Primary Navy to Hover Navy) or border color changes to indicate focus or active states.

## Shapes

The shape language is highly structured and utilizes a tiered radius system to distinguish between interactive elements and containers.

- **Base Radius (4px):** Applied to interactive elements like buttons, inputs, and list rows.
- **Container Radius (8px):** Applied to larger structural elements including cards, side panels, and tables.
- **Pill Radius (9999px):** Strictly reserved for status badges, tags, and initials-based avatars to provide a distinct visual contrast against rectangular functional elements.

## Components

### Buttons & Inputs
- **Primary Action:** Solid Navy `#000B43` background with white text. 4px border radius. 
- **Inputs:** 1px `#E9ECEF` border, 4px radius, white background. Focus state switches border to Primary Navy.

### Cards & Panels
- **Standard Card:** 8px radius, white background, 1px `#E9ECEF` border. 
- **Thematic Cards:** To denote specific brands (Veeba, Wok Tok, Zyro), a 4px solid top border in the respective brand accent color is applied to the card container.

### Status & Feedback
- **Badges:** Pill-shaped (`9999px`) with `label-sm` text. Use `success` green and `error` red backgrounds with high-contrast text.
- **Progress Bars:** Use a light grey track with a brand-accented fill (e.g., Zyro Teal) to show course completion.

### Lists & Tables
- **Tables:** Contained within 8px radius borders. Header rows use `surface-grey` (#F3F4F5). 
- **Option Rows:** Individual selectable rows in a list should use a 4px radius on hover with a subtle `surface-grey` background shift.

### Avatars
- Circular (9999px) containers using `surface-grey` with centered initials in `text-secondary`.