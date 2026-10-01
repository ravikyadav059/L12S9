<?php

use App\Models\Publication;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }
}; ?>
 
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    Hello
</div>
