<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use App\Support\Reorder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Categories')]
class Manage extends Component
{
    public string $name = '';

    public string $color = 'amber';

    public ?int $editingId = null;

    public string $editName = '';

    public string $editColor = 'amber';

    /** The same name and colour rules apply to the add form and the inline edit form. */
    private function fieldRules(string $nameField, string $colorField): array
    {
        return [
            $nameField => 'required|string|max:60',
            $colorField => ['required', Rule::in(Category::COLORS)],
        ];
    }

    public function add(): void
    {
        $data = $this->validate($this->fieldRules('name', 'color'));

        Category::createLast($data['name'], $data['color']);
        $this->reset('name');
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->editName = $category->name;
        $this->editColor = $category->color;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editName');
        $this->resetErrorBag();
    }

    public function update(): void
    {
        $data = $this->validate($this->fieldRules('editName', 'editColor'));

        Category::findOrFail($this->editingId)->update(['name' => $data['editName'], 'color' => $data['editColor']]);
        $this->cancelEdit();
    }

    public function delete(int $id): void
    {
        Category::findOrFail($id)->delete();
        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }

    public function move(int $id, int $direction): void
    {
        $ordered = Reorder::swapId(Category::ordered()->pluck('id')->all(), $id, $direction);
        foreach ($ordered ?? [] as $position => $categoryId) {
            Category::whereKey($categoryId)->update(['sort_order' => $position]);
        }
    }

    public function render()
    {
        return view('livewire.categories.manage', [
            'categories' => Category::withCount('recipes')->ordered()->get(),
            'colors' => Category::COLORS,
        ]);
    }
}
