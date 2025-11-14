## Filament 4

List of important changes in Filament v4 vs Filament v3:

- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Validation rule `unique()` has `ignoreRecord: true` by default, no need to specify it.
- Don't use full namespaces when referencing Filament classes like `Filament\Forms\Components\DatePicker`. Always put
the namespaces in `use` section on top and use only classname instead of full path.
- If you create custom Blade files with Tailwind classes, you need to create a custom theme and specify the folder of
those Blade files in theme.css.
- Table Filters have `->schema()` instead of `->form()`
- `Action::make()` has `->schema()` instead of `->form()`
- Table has `->toolbarActions()` instead of `->bulkActions()`
