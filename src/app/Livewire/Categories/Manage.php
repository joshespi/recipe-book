<?php

namespace App\Livewire\Categories;

use App\Models\Category;
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

    public function add(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:60',
            'color' => ['required', Rule::in(Category::COLORS)],
        ]);

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
        $data = $this->validate([
            'editName' => 'required|string|max:60',
            'editColor' => ['required', Rule::in(Category::COLORS)],
        ]);

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
        $ordered = Category::ordered()->pluck('id')->all();
        $index = array_search($id, $ordered, true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($index === false || ! isset($ordered[$target])) {
            return;
        }

        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
        foreach ($ordered as $position => $categoryId) {
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
