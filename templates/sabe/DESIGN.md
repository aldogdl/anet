---
name: Precision Automotive System
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#414845'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#727975'
  outline-variant: '#c1c8c3'
  surface-tint: '#476459'
  primary: '#001810'
  on-primary: '#ffffff'
  primary-container: '#102d24'
  on-primary-container: '#779689'
  inverse-primary: '#aecdc0'
  secondary: '#006d2f'
  on-secondary: '#ffffff'
  secondary-container: '#5dfd8a'
  on-secondary-container: '#007232'
  tertiary: '#031427'
  on-tertiary: '#ffffff'
  tertiary-container: '#19293d'
  on-tertiary-container: '#8090a8'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#c9eadc'
  primary-fixed-dim: '#aecdc0'
  on-primary-fixed: '#032018'
  on-primary-fixed-variant: '#304c42'
  secondary-fixed: '#66ff8e'
  secondary-fixed-dim: '#3de273'
  on-secondary-fixed: '#002109'
  on-secondary-fixed-variant: '#005322'
  tertiary-fixed: '#d3e4fe'
  tertiary-fixed-dim: '#b7c8e1'
  on-tertiary-fixed: '#0b1c30'
  on-tertiary-fixed-variant: '#38485d'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-caps:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '700'
    lineHeight: 16px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 26px
    fontWeight: '700'
    lineHeight: 32px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 8px
  sm: 16px
  md: 24px
  lg: 40px
  xl: 64px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
---

## Brand & Style

This design system is engineered for the high-performance automotive aftermarket. It balances industrial precision with a premium, showroom-floor aesthetic. The visual narrative focuses on trust, mechanical expertise, and clarity. 

The style is **Modern Corporate with Glassmorphism accents**. It utilizes a high-contrast foundation to ensure technical specifications are legible, while employing subtle translucent layers to suggest modern technology and digital sophistication. The interface should feel "engineered"—every element has a clear purpose, mirroring the structural integrity of automotive components.

## Colors

The palette is anchored by **Deep Racing Green**, providing a sophisticated, grounding base that evokes heritage and stability. **Accent Green** is used sparingly for primary actions and status indicators, ensuring high visibility against darker backgrounds. 

- **Primary (#102d24):** Used for backgrounds, headers, and primary text to establish authority.
- **Accent (#25d366):** Reserved for "In Stock" indicators, primary CTAs, and success states.
- **Slate Grays:** Used for secondary metadata, borders, and disabled states to maintain a clean hierarchy.
- **Pure White (#ffffff):** Used for component surfaces (cards, modals) to maximize contrast against the neutral background.

## Typography

The system relies exclusively on **Inter** to maintain a systematic, utilitarian feel. 

- **Headlines:** Set in Bold weights with tight letter-spacing to emulate automotive branding.
- **Labels:** Small, uppercase caps are used for technical specifications (e.g., PART NO, COMPATIBILITY) to differentiate data from descriptive text.
- **Body:** Standardized on a 14px/16px scale for high information density without sacrificing legibility.

## Layout & Spacing

The layout follows a **Fluid Grid** model with a maximum container width of 1440px for desktop. 

- **Desktop:** 12-column grid with 24px gutters. Content cards should typically span 3 or 4 columns.
- **Mobile:** Single column with 16px side margins. 
- **Rhythm:** All spacing (padding, margins) must be multiples of the 4px base unit to maintain mechanical alignment. Use 24px (md) for standard internal card padding.

## Elevation & Depth

Hierarchy is established through a combination of **Ambient Shadows** and **Glassmorphism**.

- **Level 1 (Cards):** Pure white backgrounds with a subtle, 12% opacity slate-gray shadow (0px 4px 12px) and a 1px border (#e2e8f0).
- **Level 2 (Overlays/Modals):** Backdrop-blur (12px) with a semi-transparent white fill (80% opacity) to create a "glass" effect that keeps the user grounded in the catalog context.
- **Interactive States:** On hover, cards should lift slightly (shadow increase) and the border color should shift to the Accent Green.

## Shapes

The shape language is characterized by **large, friendly radii** that soften the technical nature of the product.

- **Primary Components:** Cards and large containers use an 18px (`rounded-xl`) corner radius.
- **Small Components:** Buttons and input fields use an 8px (`rounded-md`) radius.
- **Icons:** Should be housed in circular containers or use rounded stroke-ends to match the UI's curvature.

## Components

- **Product Cards:** Must feature a high-resolution image on a light gray neutral background, a bold title, and a technical spec grid using `label-caps`.
- **Buttons:** 
  - *Primary:* Solid Deep Green with white text.
  - *Secondary:* Ghost style with 1px Deep Green border.
  - *Success (Add to Cart):* Solid Accent Green with Deep Green text for maximum "pop."
- **Status Chips:** Small, pill-shaped indicators for stock status. "In Stock" uses a light tint of Accent Green with dark green text.
- **Input Fields:** Large 48px height for accessibility, utilizing a subtle 1px slate border that thickens and turns Deep Green on focus.
- **Badges:** Overlaid on the top-right of product images for "New" or "OEM" labels using `label-caps` and the secondary green.
- **Compatibility Checker:** A specialized component with a glassmorphic background used at the top of pages to filter parts by vehicle make/model.