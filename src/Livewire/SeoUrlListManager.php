<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Seo\Models\SeoUrlList;

class SeoUrlListManager extends Component
{
    // Create/Edit modal
    public bool $showListModal = false;
    public ?int $editingListId = null;
    public string $listName = '';
    public string $listDescription = '';

    // -------------------------------------------------------------------------
    // List CRUD
    // -------------------------------------------------------------------------

    public function openCreateModal(): void
    {
        $this->editingListId = null;
        $this->listName = '';
        $this->listDescription = '';
        $this->showListModal = true;
    }

    public function openEditModal(int $id): void
    {
        $list = SeoUrlList::findOrFail($id);
        $this->editingListId = $list->id;
        $this->listName = $list->name;
        $this->listDescription = $list->description ?? '';
        $this->showListModal = true;
    }

    public function saveList(): void
    {
        $this->validate([
            'listName' => 'required|string|max:255',
            'listDescription' => 'nullable|string|max:1000',
        ]);

        if ($this->editingListId) {
            $list = SeoUrlList::findOrFail($this->editingListId);
            $list->update([
                'name' => $this->listName,
                'slug' => Str::slug($this->listName),
                'description' => $this->listDescription ?: null,
            ]);
        } else {
            SeoUrlList::create([
                'name' => $this->listName,
                'slug' => Str::slug($this->listName),
                'description' => $this->listDescription ?: null,
                'created_by' => auth()->id(),
            ]);
        }

        $this->showListModal = false;
        $this->listName = '';
        $this->listDescription = '';
        $this->editingListId = null;
    }

    public function deleteList(int $id): void
    {
        SeoUrlList::findOrFail($id)->delete();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function render()
    {
        $lists = SeoUrlList::withCount('urls')->orderBy('name')->get();

        return view('seo::livewire.seo-url-list-manager', [
            'lists' => $lists,
        ])->layout('platform::layouts.app');
    }
}
