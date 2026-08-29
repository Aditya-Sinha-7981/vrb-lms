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
  secondary: '#4758ab'
  on-secondary: '#ffffff'
  secondary-container: '#95a6ff'
  on-secondary-container: '#26388a'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#191b25'
  on-tertiary-container: '#828390'
  error: '#BA1A1A'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dee1ff'
  primary-fixed-dim: '#b9c3ff'
  on-primary-fixed: '#09164b'
  on-primary-fixed-variant: '#384379'
  secondary-fixed: '#dee1ff'
  secondary-fixed-dim: '#b9c3ff'
  on-secondary-fixed: '#001158'
  on-secondary-fixed-variant: '#2e3f92'
  tertiary-fixed: '#e1e1f0'
  tertiary-fixed-dim: '#c5c5d4'
  on-tertiary-fixed: '#191b25'
  on-tertiary-fixed-variant: '#454652'
  background: '#f8f9fa'
  on-background: '#191c1d'
  surface-variant: '#e1e3e4'
  card-bg: '#FFFFFF'
  surface-grey: '#F3F4F5'
  text-primary: '#191C1D'
  text-secondary: '#454651'
  border-base: '#E9ECEF'
  veeba-red: '#E31E24'
  veeba-warm: '#FED563'
  woktok-orange: '#F37021'
  zyro-teal: '#008080'
  success: '#28A745'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
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
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
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
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 40px
  max-width: 1280px
---

## Brand & Style

The design system is a refined **Corporate Modern** framework built for an internal enterprise environment. It prioritizes clarity, information density, and institutional trust through a disciplined "Neutral-First" aesthetic. 

The visual personality is anchored by a deep Navy foundation that signals stability, while strategically deploying vibrant brand accents (Veeba, Wok Tok, Zyro) to provide context and engagement within specific learning modules. The style avoids trendy decorative effects in favor of a clean, structured, and highly legible interface that reduces cognitive load for employees and administrators.

**Key visual principles:**
- **Minimalism:** Heavy use of white space and a restricted color palette to focus on content.
- **Precision:** Tight alignment and a strict 4px grid system.
- **Brand Anchors:** Dynamic accent colors that shift based on the current brand context.

## Colors

The color architecture is designed for AA/AAA accessibility and high legibility. It uses a hierarchy of surfaces to organize information without over-relying on heavy shadows.

- **Primary Navy (#000B43):** Reserved for high-level structural elements like headers, navigation, and primary action buttons.
- **Primary Hover (#041C72):** A slightly lighter navy used exclusively for interaction states on primary elements.
- **Backgrounds:** The interface sits on a light grey Page Background (`#F8F9FA`), while interactive content lives on pure white (`#FFFFFF`) Card Backgrounds to create natural separation.
- **Brand Accents:** Use `veeba-red`, `woktok-orange`, or `zyro-teal` as thematic indicators (e.g., top borders on cards, active progress bars, or module-specific buttons) to signal the brand context.
- **Semantic Colors:** Use `success` and `error` strictly for status feedback and validation.

## Typography

This system uses **Inter** exclusively across all levels. The typography is optimized for readability in data-intensive dashboard environments.

- **Headlines:** Use weights 600 or 700. For large displays, the negative letter spacing ensures a compact, professional look.
- **Body:** Standardized at `body-md` (16px) for optimal reading comfort. `body-lg` is reserved for introductory text or featured descriptions.
- **Labels:** Use `label-md` for interactive controls and `label-sm` for metadata or secondary annotations.
- **Color:** Headlines and primary content must use `text-primary`. Supporting information and captions use `text-secondary`.

## Layout & Spacing

The layout model is based on a **12-column fluid grid** with a maximum content width to preserve line-length readability.

- **Desktop:** Features a fixed 240px sidebar for primary navigation and a centered content area (up to 1280px) with 40px outer margins.
- **Spacing Rhythm:** All dimensions follow a 4px base unit. 
    - Use 24px (`gutter`) for the standard gap between cards and major sections.
    - Use 16px (4 units) for internal component padding.
- **Responsiveness:** At the tablet breakpoint (768px), the sidebar collapses into a hamburger menu or narrow icon bar, and outer margins reduce to 16px.

## Elevation & Depth

This system utilizes **Tonal Layers** and **Low-Contrast Outlines** to define hierarchy, avoiding the "heavy" look of traditional skeuomorphism or material shadows.

- **Level 0 (Floor):** Page background (`#F8F9FA`).
- **Level 1 (Default):** White cards or panels with a 1px solid border (`#E9ECEF`).
- **Level 2 (Interaction):** On hover or focus, apply a subtle ambient shadow (0px 4px 12px rgba(0,0,0,0.05)) to emphasize interactivity.
- **Hierarchy:** Use the `surface-grey` (#F3F4F5) to distinguish secondary regions, like a side panel or a footer, from the main white content area.

## Shapes

The shape language is structured to balance a modern feel with professional discipline. 

- **Small Components:** Buttons, input fields, and individual option rows must use the `md` radius (4px).
- **Large Containers:** Cards, panels, and tables must use the `lg` radius (8px).
- **Status Indicators:** Course badges and status chips must use the `full` pill shape (9999px) to clearly differentiate them from functional buttons.

## Components

### Buttons & Inputs
- **Buttons:** 4px radius. Primary buttons use Navy `#000B43` (Hover: `#041C72`). Secondary buttons use a Navy border with a transparent background. 
- **Inputs:** 4px radius, 1px border in `#E9ECEF`. On focus, use a 1px Navy border. Use FontAwesome 6 Free icons (e.g., `fa-search`, `fa-chevron-down`) for input adornments.

### Cards & Tables
- **Cards:** 8px radius, white background, 1px border in `#E9ECEF`. For brand differentiation, apply a 4px top border using the specific brand accent color (e.g., Veeba Red).
- **Tables:** 8px radius on the outer container. Rows should have a 1px bottom border in `#E9ECEF`. Header rows use a `surface-grey` background.

### Avatars & Status
- **Avatars:** Use a circle (9999px radius) containing user initials in `text-secondary` on a `surface-grey` background.
- **Badges:** Pill-shaped (9999px) with `label-sm` typography. 

### Assets & Icons
- **Logos:** Use inline SVGs for the VRB wordmark and specific brand logos.
- **Icons:** Use FontAwesome 6 Free names only. Standard icon color is `Icon Grey` (#757683).