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
  on-surface-variant: '#454651'
  inverse-surface: '#2e3132'
  inverse-on-surface: '#f0f1f2'
  outline: '#757683'
  outline-variant: '#c5c5d3'
  surface-tint: '#4758ab'
  primary: '#000b43'
  on-primary: '#ffffff'
  primary-container: '#041c72'
  on-primary-container: '#7889e0'
  inverse-primary: '#b9c3ff'
  secondary: '#505a9a'
  on-secondary: '#ffffff'
  secondary-container: '#adb7fe'
  on-secondary-container: '#3c4685'
  tertiary: '#2e0300'
  on-tertiary: '#ffffff'
  tertiary-container: '#520c01'
  on-tertiary-container: '#d97159'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dee1ff'
  primary-fixed-dim: '#b9c3ff'
  on-primary-fixed: '#001158'
  on-primary-fixed-variant: '#2e3f92'
  secondary-fixed: '#dee0ff'
  secondary-fixed-dim: '#bbc3ff'
  on-secondary-fixed: '#071353'
  on-secondary-fixed-variant: '#384280'
  tertiary-fixed: '#ffdad3'
  tertiary-fixed-dim: '#ffb4a4'
  on-tertiary-fixed: '#3d0600'
  on-tertiary-fixed-variant: '#7d2c1a'
  background: '#f8f9fa'
  on-background: '#191c1d'
  surface-variant: '#e1e3e4'
  veeba-red: '#E31E24'
  veeba-warm-accent: '#FED563'
  woktok-orange: '#F37021'
  zyro-teal: '#008080'
  text-slate: '#1A1A1A'
  border-subtle: '#E9ECEF'
  progress-success: '#28A745'
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
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
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
  container-max: 1280px
---

## Brand & Style

This design system is engineered for the internal Learning Management System (LMS) of a multi-brand consumer goods enterprise. The aesthetic is rooted in **Corporate Minimalism**: a clean, highly structured, and dependable visual language that prioritizes information density and clarity.

The core identity is driven by a deep navy foundation, representing the parent organization's stability. To maintain engagement across diverse brand modules (Veeba, Wok Tok, Zyro), the system utilizes "Brand Anchors"—strategic color injections that shift the UI context without breaking the underlying layout logic.

**Target Audience:** Corporate employees and HR administrators.
**Emotional Response:** Efficiency, professionalism, clarity, and institutional trust.

## Colors

The palette uses a "Neutral-First" approach. 90% of the interface sits on white (`#FFFFFF`) or light grey (`#F8F9FA`) surfaces to ensure the content—learning modules and data—remains the focus.

- **Primary (VRB Navy):** Used for global navigation, headers, and primary action buttons.
- **Brand Accents:** These are used sparingly. When a user enters a "Veeba" course, the primary button and the top indicator bar shift to `veeba-red`. Wok Tok modules utilize `woktok-orange`, and Zyro utilizes `zyro-teal`.
- **Functionality:** `text-slate` is used for all body copy to ensure AA/AAA accessibility compliance. `border-subtle` is the standard for dividing dashboard cards and table rows.

## Typography

This design system utilizes **Inter** for its exceptional legibility in data-heavy environments. The hierarchy is strictly enforced to guide users through complex training materials.

- **Headlines:** Set in bold weights with tighter letter spacing for a modern, "Swiss-style" corporate look.
- **Body:** Standardized at 16px for optimal reading on desktop displays.
- **Labels:** Used for metadata (e.g., "Time to complete," "Course Level") and UI controls. These often use `500` or `600` weights to differentiate from body text.

## Layout & Spacing

The layout is built on a **12-column fluid grid** with fixed gutters. It follows a traditional enterprise dashboard structure:

1.  **Global Header:** Fixed at the top (64px height), housing brand selection and user profile.
2.  **Side Navigation:** Fixed on the left for desktop (240px width), collapsible for tablet.
3.  **Main Content Area:** Centered with a maximum width of 1280px to prevent line lengths from becoming too long for comfortable reading.

**Spacing Rhythm:** All margins and paddings are multiples of 4px. Use 24px (6 units) for standard component spacing to maintain a high-whitespace, "airy" feel that reduces cognitive load during learning.

## Elevation & Depth

To maintain the "Basic Corporate" aesthetic, the system avoids complex shadows or glassmorphism. Instead, it uses **Tonal Layers** and **Low-Contrast Outlines**:

- **Tier 1 (Surface):** The background color (`#F8F9FA`).
- **Tier 2 (Container):** White cards (`#FFFFFF`) with a 1px solid border in `border-subtle`. This is the primary container for course content and list items.
- **Tier 3 (Interaction):** A subtle, diffused ambient shadow (0px 4px 12px rgba(0,0,0,0.05)) is applied only when a card is hovered or an element is active.

This flat-depth approach ensures the UI feels stable and integrated with common enterprise browser environments.

## Shapes

The design system uses a "Soft" shape language. While 0px corners feel too aggressive and technical, large rounded corners feel too "consumer-grade" or playful.

- **Components (Buttons, Inputs):** 0.25rem (4px) corner radius. This provides a professional, "Bootstrap-refined" look.
- **Containers (Cards, Progress Bars):** 0.5rem (8px) corner radius for larger structural elements.
- **Course Badges:** Use a pill-shape (full rounding) to differentiate status indicators from functional buttons.

## Components

### Buttons
- **Primary:** Solid fill (VRB Navy). High contrast text.
- **Brand-Specific:** When within a brand module, the primary button assumes that brand's accent color.
- **Secondary:** Transparent fill with a 1px border.

### Input Fields
- Understated styling: 1px border (`#E9ECEF`), 4px radius. 
- On focus: Border color changes to Primary Navy with a subtle 2px outer glow.

### Progress Bars
- Essential for LMS. 8px height, rounded.
- Background: `#E9ECEF`.
- Fill: `progress-success` (Green) or the active brand accent color.

### Cards
- White background, 1px border, 8px radius.
- Headers within cards should have a subtle bottom border.
- For Brand differentiation: A 4px thick top border on the card using the Brand Accent color.

### Sidebars & Navigation
- Primary Nav: Dark background (`#242E6C`) with white text for high contrast.
- Sub-navigation: Light grey background with a left-accent bar on the active item.