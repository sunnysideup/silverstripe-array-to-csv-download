# Upgrade to Silverstripe CMS 6

## Framework Requirements

**⚠️ BREAKING CHANGE**: Update Silverstripe dependency to CMS 6
- Change `composer.json` requirement from `"silverstripe/recipe-cms": "^4 || ^5"` to `"silverstripe/recipe-cms": "^6.0"`

## API Changes

**⚠️ BREAKING CHANGE**: Namespace relocations
- Replace `SilverStripe\ORM\SS_List` with `SilverStripe\Model\List\SS_List`
- Replace `SilverStripe\View\ViewableData` with `SilverStripe\Model\ModelData`

**⚠️ BREAKING CHANGE**: Base class updated
- `ArrayToCSV` now extends `ModelData` instead of `ViewableData` (src/Api/ArrayToCSV.php:42)
- Review any code that depends on `ViewableData`-specific methods

## Configuration

- Update `composer.json` to use array syntax for `suggested` key (line 19: change `{}` to `[]`)
