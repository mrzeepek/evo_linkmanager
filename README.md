# EvoLinkManager - PrestaShop Link Management Module

## Overview

EvoLinkManager is a professional PrestaShop module designed to simplify the management of links in your store. This module allows administrators to create, organize, and display various types of links including custom URLs, contact pages, and CMS pages at specific locations in your theme, without requiring any code changes.

## Features

- **Multiple Link Types**: Support for custom URLs, contact links, and CMS page links
- **Easy Management**: Intuitive back-office interface to create, edit, and organize links
- **Theme Placements**: Define specific locations in your theme where links should appear
- **No-Code Solution**: Non-technical users can change links at specific theme locations
- **Position Control**: Set display order with drag-and-drop positioning
- **Activation Toggle**: Easily enable or disable individual links without deleting them
- **Footer Integration**: Links are automatically displayed in the site footer
- **Custom Styling**: Add custom CSS to match your theme
- **Default Links**: Comes preconfigured with Contact and FAQ links

## Technical Specifications

- Compatible with PrestaShop 8.0+
- PHP 8.0 or higher
- Modern architecture using CQRS pattern
- Utilizes PrestaShop's grid system for the admin interface
- Built following Evolutive PHP coding standards

## Installation

1. Upload the `evo_linkmanager` folder to your PrestaShop `/modules` directory
2. Navigate to the Modules section in your PrestaShop back-office
3. Find "Link Manager" in the module list and click "Install"
4. Configure the module based on your needs

## Configuration

### Managing Links

1. Go to **Modules > Module Manager > Link Manager > Configure**
2. Navigate to the "Links" tab or click the "Manage Links" button
3. Use the interface to create new links, edit existing ones, or change their order

### Managing Theme Placements

1. Go to **Modules > Module Manager > Link Manager > Configure**
2. Navigate to the "Placements" tab or click the "Manage Placements" button
3. Create placements that correspond to specific locations in your theme
4. Associate links with these placements

### Link Types

The module supports three types of links:

- **Custom**: Create links to any URL you specify
- **Contact**: Link to your contact page (default: https://support.raviday-barbecue.com/hc/fr/requests/new)
- **CMS Page**: Link to any CMS page in your PrestaShop store (ideal for FAQ, Terms & Conditions, etc.)

### Custom Styling

You can add custom CSS to modify the appearance of the links:

1. Go to **Modules > Module Manager > Link Manager > Configure**
2. Find the "Custom CSS" field
3. Add your custom CSS rules
4. Save your changes

## Using Placements in Your Theme

There are two ways to use placement links in your templates:

### 1. Using the Smarty Function (recommended)

Add this code to any .tpl file in your theme:

```smarty
<a href="{get_evo_link_by_placement identifier="your_placement_id"}" class="your-class">Your Link Text</a>
```

Replace `your_placement_id` with the identifier you defined in the Placements section.

### 2. Using the Smarty Variable

All placement URLs are also available as Smarty variables:

```smarty
<a href="{$evo_placement_urls.your_placement_id|default:'#'}" class="your-class">Your Link Text</a>
```

### Example Use Case

For a "Discover Reviews" button in your theme:

1. Create a placement with identifier `discover_reviews` in the back-office
2. Associate it with a link pointing to your reviews page
3. In your theme template (e.g., `avis.tpl`), replace:

```html
<a href="#" class="btn btn-lg btn-inverse-primary px-md-1 w-auto w-md-100">Découvrir les avis</a>
```

With:

```smarty
<a href="{get_evo_link_by_placement identifier="discover_reviews"}" class="btn btn-lg btn-inverse-primary px-md-1 w-auto w-md-100">Découvrir les avis</a>
```

Now, whenever you need to change the link destination, you can do it directly from the back-office without touching the code!

## Display in Theme

By default, links are displayed in the footer of your store. The module uses the `displayFooter` hook.

If you want to display the links in a different position, you can use the following hook in your theme:

```smarty
{hook h="displayFooter"}
```

## Support

For technical support, feature requests, or bug reports, please contact us at:

- Email: contact@evolutive.fr
- Website: https://evolutive.fr

## License

EvoLinkManager is proprietary software. Unauthorized copying, distributing, or modifying this module is prohibited.

Copyright © 2024 Evolutive Group. All rights reserved.
