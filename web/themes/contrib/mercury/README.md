# Mercury Theme

Mercury is a component-based Drupal theme, providing a modern and flexible starting point for site owners to build scalable and efficient websites using [Drupal Canvas](/project/canvas).

## Getting started

To use Mercury, you can install it via Composer, like any other Drupal theme. But Mercury is designed to be copied, rather than used as a contributed theme or base theme, and you should not assume that future updates will be compatible with your site.

To create the clone to use for your site, use Drupal core's starter kit tool:

```shell
cd drupal/web
php core/scripts/drupal generate-theme my_theme --name="My Custom Theme" --description="A customized version of Mercury." --starterkit=mercury
```

This will create a copy of Mercury called `my_theme`, and place it in `themes/my_theme`. This theme is yours, and you can customize it in any way you see fit!

To install it in Drupal, either visit the `/admin/appearance` page, or run `drush theme:enable my_theme` at the command line.

You can then remove the contributed version via Composer with `composer remove drupal/mercury`.

### Sub-theming

**Don't create your custom theme as a sub-theme of Mercury.** Mercury is meant to be used as a starter kit, and does not provide backward compatibility. This allows us to rapidly innovate, iterate, and improve. If you create a sub-theme of Mercury, it is likely to break in the future.

## Customizing

### Fonts & colors

To change the fonts or colors in `my_theme`, edit the `theme.css` file. Changes to `theme.css` do not require a CSS rebuild, but you may need to clear the cache.

### Custom components

Mercury uses [single-directory components](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components) and comes with a variety of commonly used components. You can add new components and modify existing ones, but be sure to rebuild the CSS when you make changes.

## Building CSS

Mercury uses [Tailwind](https://tailwindcss.com) to simplify styling by using classes to compose designs directly in the markup.

If you want to customize the Tailwind-generated CSS, install the development tooling dependencies by running the following command in your theme's directory:

```shell
npm install
```

If you modify CSS files or classes in a Twig template, you need to rebuild the CSS:

```bash
npm run build
```

For development, you can watch for changes and automatically rebuild the CSS:

```bash
npm run dev
```

## Code Formatting

Mercury uses [Prettier](https://prettier.io) to automatically format code for consistency. The project is configured with plugins for Tailwind CSS and Twig templates.

For the best experience, [set up Prettier in your editor](https://prettier.io/docs/editors) to automatically format files on save.

To format all files in the project:

```bash
npm run format
```

To check if files are formatted correctly without making changes:

```bash
npm run format:check
```

**Note**: Some files are excluded from formatting via `.prettierignore`, such as Drupal's `html.html.twig` template, which contains placeholder tokens that break Prettier's HTML parsing.

## Component JavaScript

`lib/component.js` has two classes you can use to nicely encapsulate your component JS without pasting all the `Drupal.behaviors.componentName` boilerplate into every file. The steps are:

1. Extend the `ComponentInstance` class to a new class with the code for your component.
2. Create a new instance of the `ComponentType` class to automatically activate all the component instances on that page.

For example, here's a stub of `accordion.js`:

```js
import { ComponentType, ComponentInstance } from "../../lib/component.js";

// Make a new class with the code for our component.
//
// In every method of this class, `this.el` is an HTMLElement object of
// the component container, whose selector you provide below. You don't
// have an array of elements that you have to `.forEach()` over yourself;
// the ComponentType class handles all that for you.
class Accordion extends ComponentInstance {
  // Every subclass must have an `init` method to activate the component.
  init() {
    this.el.querySelector(".accordion--content").classList.toggle("visible");
    this.el.addClass("js");
  }

  // You may also implement a `remove()` method to clean up when a component is
  // about to be removed from the document. This will be invoked during the
  // `detach()` method of the Drupal behavior.

  // You can create as many other methods as you want; in all of them,
  // `this.el` represents the single instance of the component. Any other
  // properties you add to `this` will be isolated to that one instance
  // as well.
}

// Now we instantiate ComponentType to find the component elements and run
// our script.
new ComponentType(
  // First argument: The subclass of ComponentInstance we just created above.
  Accordion,
  // Second argument: A camel-case unique ID for the behavior (and for `once()`
  // if applicable).
  "accordion",
  // Third argument: A selector for `querySelectorAll()`. All matching elements
  // on the page get their own instance of the subclass you created, each of
  // which has `this.el` pointing to one of those matches.
  ".accordion",
);
```

This is all the code required to be in each component. The ComponentType instance handles finding the elements, running them through `once` if available, and adding them to `Drupal.behaviors`.

All the objects created this way will be stored in a global variable so you can do stuff with them later. Since the `namespace` variable at the top of component.js is `mercuryComponents`, you would find the Accordion's ComponentType instance at `window.mercuryComponents.accordion`.

Furthermore, `window.mercuryComponents.accordion.instances` is an array of all the ComponentInstance objects, and `window.mercuryComponents.accordion.elements` is an array of all the component container elements.

## Known issues

Canvas's code components are currently not compatible with Tailwind-based themes like Mercury, and creating a code component will break Mercury's styling. This will be fixed in [#3549628], but for now, here's how to work around it:

1. In Canvas's in-browser code editor, open the Global CSS tab.
2. Paste the contents of your custom theme's `theme.css` into the code editor. It must be at the top.
3. Paste the contents of your custom theme's `main.css` into the code editor, removing all the `@import` statements at the top first. It must come _after_ the contents of `theme.css`.
4. Save the global CSS.

## Getting help

If you have trouble or questions, please [visit the issue queue](https://www.drupal.org/project/issues/mercury?categories=All) or find us on [Drupal Slack](https://www.drupal.org/community/contributor-guide/reference-information/talk/tools/slack), in the `#drupal-cms-support` channel.

## Roadmap

Mercury is under active development. Planned improvements include more components, better customization options, and [Storybook support](https://www.drupal.org/project/mercury/issues/3562711). If you want to contribute to Mercury, check out the `#drupal-cms-development` channel in Drupal Slack.
