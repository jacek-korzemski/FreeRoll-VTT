<?php

namespace App\Livewire;

use App\Exceptions\VttSourceMissingException;
use App\Models\VttTable;
use App\Services\TableProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class TablesDashboard extends Component
{
    public string $name = '';

    public string $player_password = '';

    public string $gm_password = '';

    public string $language = 'pl';

    public ?int $editingId = null;

    public string $edit_player_password = '';

    public string $edit_gm_password = '';

    public string $edit_language = 'pl';

    public ?int $confirmingDeleteId = null;

    public function createTable(TableProvisioner $provisioner): void
    {
        $validated = $this->validate($this->createRules());

        try {
            $provisioner->create(Auth::user(), $validated);
        } catch (VttSourceMissingException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->reset('name', 'player_password', 'gm_password');
        $this->language = 'pl';
        session()->flash('status', 'Stół został utworzony.');
    }

    public function startEdit(int $tableId): void
    {
        $table = $this->ownedTable($tableId);
        $this->editingId = $table->id;
        $this->edit_player_password = $table->player_password;
        $this->edit_gm_password = $table->gm_password;
        $this->edit_language = $table->language;
        $this->confirmingDeleteId = null;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset('edit_player_password', 'edit_gm_password');
        $this->edit_language = 'pl';
    }

    public function saveSettings(TableProvisioner $provisioner): void
    {
        $table = $this->ownedTable((int) $this->editingId);

        $validated = $this->validate([
            'edit_player_password' => $this->passwordRules(),
            'edit_gm_password' => $this->passwordRules(),
            'edit_language' => ['required', Rule::in(['en', 'pl'])],
        ]);

        $provisioner->updateSettings($table, [
            'player_password' => $validated['edit_player_password'],
            'gm_password' => $validated['edit_gm_password'],
            'language' => $validated['edit_language'],
        ]);

        $this->cancelEdit();
        session()->flash('status', 'Ustawienia stołu zapisane.');
    }

    public function confirmDelete(int $tableId): void
    {
        $this->ownedTable($tableId);
        $this->confirmingDeleteId = $tableId;
        $this->editingId = null;
    }

    public function deleteTable(TableProvisioner $provisioner): void
    {
        $table = $this->ownedTable((int) $this->confirmingDeleteId);
        $provisioner->destroy($table);
        $this->confirmingDeleteId = null;
        session()->flash('status', 'Stół został usunięty.');
    }

    public function render(TableProvisioner $provisioner)
    {
        $user = Auth::user();
        $tables = $user->vttTables()->with('user')->latest()->get();
        $max = (int) config('vtt.max_tables');

        return view('livewire.tables-dashboard', [
            'tables' => $tables,
            'count' => $tables->count(),
            'max' => $max,
            'canCreate' => $tables->count() < $max,
            'sourceReady' => $provisioner->sourceIsReady(),
            'sourcePath' => $provisioner->sourcePath(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Podaj nazwę stołu.',
            'name.max' => 'Nazwa stołu może mieć maksymalnie :max znaków.',
            'player_password.required' => 'Podaj hasło gracza.',
            'player_password.min' => 'Podaj hasło gracza.',
            'player_password.max' => 'Hasło gracza może mieć maksymalnie :max znaków.',
            'player_password.not_regex' => 'Hasło gracza nie może zawierać znaków nowej linii.',
            'gm_password.required' => 'Podaj hasło Mistrza Gry.',
            'gm_password.min' => 'Podaj hasło Mistrza Gry.',
            'gm_password.max' => 'Hasło Mistrza Gry może mieć maksymalnie :max znaków.',
            'gm_password.not_regex' => 'Hasło Mistrza Gry nie może zawierać znaków nowej linii.',
            'language.required' => 'Wybierz język interfejsu stołu.',
            'language.in' => 'Wybierz język: polski lub angielski.',
            'edit_player_password.required' => 'Podaj hasło gracza.',
            'edit_player_password.min' => 'Podaj hasło gracza.',
            'edit_player_password.max' => 'Hasło gracza może mieć maksymalnie :max znaków.',
            'edit_player_password.not_regex' => 'Hasło gracza nie może zawierać znaków nowej linii.',
            'edit_gm_password.required' => 'Podaj hasło Mistrza Gry.',
            'edit_gm_password.min' => 'Podaj hasło Mistrza Gry.',
            'edit_gm_password.max' => 'Hasło Mistrza Gry może mieć maksymalnie :max znaków.',
            'edit_gm_password.not_regex' => 'Hasło Mistrza Gry nie może zawierać znaków nowej linii.',
            'edit_language.required' => 'Wybierz język interfejsu stołu.',
            'edit_language.in' => 'Wybierz język: polski lub angielski.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'player_password' => $this->passwordRules(),
            'gm_password' => $this->passwordRules(),
            'language' => ['required', Rule::in(['en', 'pl'])],
        ];
    }

    /**
     * @return list<string>
     */
    private function passwordRules(): array
    {
        return ['required', 'string', 'min:1', 'max:128', 'not_regex:/[\\r\\n]/'];
    }

    private function ownedTable(int $id): VttTable
    {
        return Auth::user()->vttTables()->with('user')->whereKey($id)->firstOrFail();
    }
}
