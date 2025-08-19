# Simple Global Filter

This module provides an easy way to add global filters, based on taxonomies, in order to filter content on your side.

## Features
* Create a global filter and display it in the site using a block
* Use the global filters for setting up a block visibility (via Conditions plugins)
* Integrate it with Views
* Change the filter globally using GET parameters

## Installation
- Install this module as any other:
`composer require drupal/simple_global_filter`

## How to use and configure

The global filter extends a taxonomy vocabulary, so you need to have a vocabulary created first. Then, go
to /admin/structure/global_filter and create a Global Filter.

The following options are available:
  - Label, for internal use.
  - Vocabulary: select the vocabulary that will be used as base for this global filter.
  - Default value: Select which value, from the terms in the vocabulary, will be used by default.
  - Use alias value: you can set the global filter value using a GET parameter. This is useful for sharing the URL of your site with a predefined value for the global filter. The term id is used by default, but it can be ugly and non ideal for SEO, so this module gives the option of using a term alias name, instead of the id. In order to use it you need first to create a text field of type 'Text (Plain)' in the vocabulary, which will contain the alias per term.
  - Choose the alias field: this option is only active if you chose the 'Use alias value' option. Select the field that, attached to the vocabulary, will have the alias name of the term.
  - Display in URL: once the filter is submitted, the page reloads with the filter applied. Using this option, the value of the filter is also added to the URL, so you can share that page if needed. Using this option you can control if the filter value is added, or not, to the URL. Keep in mind that, as this module supports setting the value using a GET parameter, there may be conflicts of value using this option.
  - Display ALL items: select this option if you want to add an extra value option in the vocabulary in order to display all items. If this option is true, there will be an option to choose (also as a default value, after reloading the form). Then, when using the filter's value in both the views and conditional plugins, if the option of Display ALL values is chosen, the filters will return all elements that have been configured to use the global filter. See examples option later for an example.
  - Label for the Display all option: the label that your end users will see for selecting all items.
  - Storing mode: decides how the filter will be stored locally for your user.
    - Session: This is the default. Stores the value in the user's session. As a disadvantage, annonymous users will create a session, which can be conflict with caching systems like Drupal's page_cache or Varnish.
    - Cookie: The global filter value is stored in a cookie in the user's browser. As a disadvantage, you need to inform your users that the site is using cookies. If your users are using a cookie blocker, this functionality may not work correctly.

Once the global filter is created, a block will be created after it. Place it wherever you want. This block will show a list of filter options, one per vocabulary term (and one more if the Display ALL items options has been selected). This list is a form, and it will be submited once the user selects an option. You can alter this default behaviour by altering the form id 'select_global_filter'.

Also, a new condition will be created for each global filter. You can use this feature in an element that support plugin conditions, such as a block' visibility settings.

Also, new filter options are available in Views. For using this feature, configure an entity that uses a field which is a reference to a term, which is under a global filter. So, for example, create a Vocabulary called Countries and add some countries. Then, in a content type, create a field that references to this Country vocabulary. And then create a view which lists the same content type and try to filter by countries. In the filter panel, select 'Global' group and you should see there the filters provided by this module. You may need to remove the caching for that view display in order to see the changes.

If you want to use the global filters programatically, invoke the 'simple_global_filter.global_filter' service.

### GET parameter
It is possible to set the value of the global filter using a GET parameter. Just add the machine name of the global filter as a parameter, equal the the id of the term for filtering. See Example section for an example of this.

## Example of use
1.- Create a Vocabulary named Countries. Add terms Russia, Finland and Sweden.

2.- Create a Global Filter named Countries (/admin/structure/global_filter) and select the vocabulary Countries as the base. Set the default value to Russia. Leave rest of the options unchecked.

3.- Go to Block Layout (admin/structure/block) and add the global filter block. Click on Place block in one region (for example 'Featured top') and look for a Countries block provided by Simple Globa Filter.

Now let's create some content, starting with blocks.
4.- Create 3 new custom blocks (admin/structure/block/block-content), one per country. You can name them with the name of the country and in the body something like 'This will be displayd if country COUNTRY is selected', changing the value of COUNTRY, so they are always different.

5.- Add the blocks to the block layout (admin/structure/block). So, for example, for Finland, click on Place block, select the Finland custom block you just created from the list, and add it. In the Visibility settings option, find the group of Countries (Global Filter) and select the 'Finland' option only. Repeat the same with the other 2 blocks, just changing the visibility setting to each correct option.

6.- Now go to the frontpage and you should see the global filter block and the Russia block, as it was selected by default. Choose the value Finland in the global filter. The page is submitted and only Finland block is shown. Select Sweden value, the Finland block dissappears and the Sweden block is displayed.

7.- Go back to configure the global filter (admin/structure/global_filter/countries/edit) and select the option 'Display ALL items'. In the option 'Label for the Display all option' type 'All countries'. Save the form and
load it again. In the field 'Default value for vocabulary Countries' select 'All countries' option. Save again.

8.- Go back to the frontpage and reload it. You will see the option 'All countries' as an option. Select it, and then all 3 country blocks should appear.

9.- Go back to configure the global filter (admin/structure/global_filter/countries/edit). Select the option Display in URL. Go back to frontpage and reload. When you submit the global filter, check the URL. The parameter countries=X is added, where X is the id of the term. You can copy this URL, and paste it in another browser (for example, incognito mode), and load the URL. The filter will be applied automatically.

10.- Go to the Vocabulary manage fields panel (admin/structure/taxonomy/manage/countries/overview/fields) and add a new field of type Text (Plain) and name Alias. Then, for each of the 3 terms (Russia, Finland and Sweden) edit the term and add the name of each country in the alias field.

11.- Go back to configure the global filter (admin/structure/global_filter/countries/edit). Select the option 'Use alias value'. Then go back to the frontpage and submit the global filter. Check the URL, the alias will be used instead of the id of the term (E.g. countries=russia).

12.- Create (or reuse) a content type. For example, the Article content type. Add a field of type taxonomy term reference and as target the Countries vocabulary. Name this field 'Country'. Then create, as with the blocks, 3 nodes of type Article, for each of the countries. Make sure that, for each, the reference to the Countries vocabulary is also set up.

13.- Create a new view with a display block. This view will list content of type Article, and then add a global filter value. So, in the section 'Filter criteria' of the view, click on Add and a long list of filter options will be available. Search for 'global filter' or select 'Global' in the Category filter. You should see there a filter named 'Global filter for node.field_country'. Select it and, in the upcoming settings, select the global filter 'Countries'. Apply and save.

14.- Go back to the block layout (admin/structure/block) and click on Place Block (for example under Content region). As visibility setting, make sure it takes the default, so it's shown always.

15.- Finally, go to the front page again and reload it. You should see the newly created block view with listing the content you created (the 3 articles). Now, try to submit the global filter with a different option. The list of content should change accordingly.
## Troubleshooting
This module has been tested to work in a clean environment (fresh Drupal 9 installation). The most common problem that it has given is when dealing with caching, specially when rendering the list of items. If it is not working for you, check first that you're have disabled the caching layers that both blocks (via conditional plugin) and views provide by default. Also do not try from an annonymous user, as there are more cache layers involved.

# Author: Alberto G. Viu (alberto@exove.fi) for Exove Ltd.
