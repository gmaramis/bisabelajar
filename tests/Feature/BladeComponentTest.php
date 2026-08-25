<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class BladeComponentTest extends TestCase
{
    public function test_button_renders_correct_variants_and_sizes(): void
    {
        $primary = $this->blade('<x-button variant="primary" size="md">Simpan Data</x-button>');
        $primary->assertSee('bg-blue-600')
            ->assertSee('min-h-[44px]')
            ->assertSee('Simpan Data')
            ->assertSee('type="submit"', false);

        $danger = $this->blade('<x-button variant="danger" size="sm" type="button">Hapus</x-button>');
        $danger->assertSee('bg-rose-600')
            ->assertSee('min-h-[36px]')
            ->assertSee('Hapus')
            ->assertSee('type="button"', false);

        $link = $this->blade('<x-button variant="secondary" href="https://bisabelajar.id">Buka Link</x-button>');
        $link->assertSee('<a href="https://bisabelajar.id"', false)
            ->assertSee('bg-white')
            ->assertSee('Buka Link');
    }

    public function test_badge_renders_correct_semantic_colors_and_dot(): void
    {
        $success = $this->blade('<x-badge variant="success" dot>Mastered</x-badge>');
        $success->assertSee('bg-emerald-50')
            ->assertSee('bg-emerald-500')
            ->assertSee('Mastered');

        $warning = $this->blade('<x-badge variant="warning">In Progress</x-badge>');
        $warning->assertSee('bg-amber-50')
            ->assertSee('In Progress');

        $primary = $this->blade('<x-badge variant="primary" size="lg">Level 2</x-badge>');
        $primary->assertSee('bg-blue-50')
            ->assertSee('text-sm')
            ->assertSee('Level 2');
    }

    public function test_input_and_label_render_with_error_and_required_indicators(): void
    {
        $label = $this->blade('<x-label value="Nama Lengkap" required />');
        $label->assertSee('Nama Lengkap')
            ->assertSee('*');

        $input = $this->blade('<x-input name="email" type="email" placeholder="user@example.com" required />');
        $input->assertSee('name="email"', false)
            ->assertSee('type="email"', false)
            ->assertSee('placeholder="user@example.com"', false)
            ->assertSee('required', false);
    }

    public function test_form_group_combines_label_and_help_text(): void
    {
        $group = $this->blade('
            <x-form-group label="Username" name="username" required help="Gunakan huruf kecil tanpa spasi.">
                <x-input name="username" />
            </x-form-group>
        ');

        $group->assertSee('Username')
            ->assertSee('*')
            ->assertSee('name="username"', false)
            ->assertSee('Gunakan huruf kecil tanpa spasi.');
    }

    public function test_select_and_textarea_render_cleanly(): void
    {
        $select = $this->blade('
            <x-select name="role" required>
                <option value="student">Student</option>
                <option value="tutor">Tutor</option>
            </x-select>
        ');
        $select->assertSee('name="role"', false)
            ->assertSee('Student')
            ->assertSee('Tutor');

        $textarea = $this->blade('<x-textarea name="bio" rows="5">Catatan profil</x-textarea>');
        $textarea->assertSee('name="bio"', false)
            ->assertSee('rows="5"', false)
            ->assertSee('Catatan profil');
    }

    public function test_checkbox_renders_with_label_and_description(): void
    {
        $checkbox = $this->blade('<x-checkbox name="agree" label="Saya setuju" description="Syarat dan ketentuan berlaku." checked />');
        $checkbox->assertSee('name="agree"', false)
            ->assertSee('checked', false)
            ->assertSee('Saya setuju')
            ->assertSee('Syarat dan ketentuan berlaku.');
    }

    public function test_card_renders_with_header_actions_and_footer(): void
    {
        $card = $this->blade('
            <x-card title="Modul Algoritma" subtitle="Pemrograman Dasar">
                <x-slot:actions>
                    <x-button size="sm">Aksi</x-button>
                </x-slot:actions>
                <p>Isi modul pembelajaran.</p>
                <x-slot:footer>
                    <span>Footer info</span>
                </x-slot:footer>
            </x-card>
        ');

        $card->assertSee('Modul Algoritma')
            ->assertSee('Pemrograman Dasar')
            ->assertSee('Aksi')
            ->assertSee('Isi modul pembelajaran.')
            ->assertSee('Footer info');
    }

    public function test_stat_card_renders_metric_value_trend_and_icon(): void
    {
        $stat = $this->blade('
            <x-stat-card title="Tingkat Kelulusan" value="94.8%" tag="Live" trend="+5.2%" trendUp="true" icon="academic-cap" />
        ');

        $stat->assertSee('Tingkat Kelulusan')
            ->assertSee('94.8%')
            ->assertSee('Live')
            ->assertSee('+5.2%');
    }

    public function test_modal_renders_with_name_and_close_button(): void
    {
        $modal = $this->blade('
            <x-modal name="test-modal" title="Konfirmasi Hapus">
                <p>Apakah Anda yakin ingin menghapus?</p>
            </x-modal>
        ');

        $modal->assertSee('test-modal')
            ->assertSee('Konfirmasi Hapus')
            ->assertSee('Apakah Anda yakin ingin menghapus?');
    }

    public function test_toast_and_theme_toggle_render_proper_attributes(): void
    {
        $toast = $this->blade('<x-toast />');
        $toast->assertSee('@toast.window', false);

        $toggle = $this->blade('<x-theme-toggle />');
        $toggle->assertSee('toggleTheme()', false)
            ->assertSee('dusk="theme-toggle-btn"', false);
    }

    public function test_alert_renders_variants_and_dismiss_button(): void
    {
        $alert = $this->blade('
            <x-alert variant="warning" title="Perhatian" dismissible>
                Sistem sedang dalam mode pemeliharaan.
            </x-alert>
        ');

        $alert->assertSee('Perhatian')
            ->assertSee('Sistem sedang dalam mode pemeliharaan.')
            ->assertSee('role="alert"', false)
            ->assertSee('dismissed = true', false);
    }

    public function test_search_input_renders_with_model_and_clear_button(): void
    {
        $search = $this->blade('<x-search-input placeholder="Cari unit materi..." name="q" value="Laravel" />');
        $search->assertSee('name="q"', false)
            ->assertSee('placeholder="Cari unit materi..."', false)
            ->assertSee('query = \'\'', false);
    }

    public function test_table_renders_header_content_and_empty_state(): void
    {
        $tableWithData = $this->blade('
            <x-table>
                <x-slot:header>
                    <tr><th>Nama</th><th>Status</th></tr>
                </x-slot:header>
                <tr><td>Pengenalan Array</td><td><x-badge variant="success">Aktif</x-badge></td></tr>
            </x-table>
        ');

        $tableWithData->assertSee('Nama')
            ->assertSee('Status')
            ->assertSee('Pengenalan Array')
            ->assertSee('Aktif');

        $emptyTable = $this->blade('<x-table empty emptyMessage="Data kursus belum tersedia." />');
        $emptyTable->assertSee('Data kursus belum tersedia.');
    }

    public function test_dropdown_and_dropdown_link_render_correctly(): void
    {
        $dropdown = $this->blade('
            <x-dropdown>
                <x-slot:trigger>
                    <button>Menu</button>
                </x-slot:trigger>
                <x-slot:content>
                    <x-dropdown-link href="/profile" icon="user">Profil</x-dropdown-link>
                    <x-dropdown-link href="/delete" danger icon="trash">Hapus</x-dropdown-link>
                </x-slot:content>
            </x-dropdown>
        ');

        $dropdown->assertSee('Menu')
            ->assertSee('Profil')
            ->assertSee('href="/profile"', false)
            ->assertSee('Hapus')
            ->assertSee('text-rose-600');
    }

    public function test_navbar_and_sidebar_render_with_title_and_slots(): void
    {
        $navbar = $this->blade('
            <x-navbar title="BisaBelajar Test">
                <x-slot:navLinks>
                    <a href="/courses">Courses</a>
                </x-slot:navLinks>
            </x-navbar>
        ');

        $navbar->assertSee('BisaBelajar Test')
            ->assertSee('Courses');

        $sidebar = $this->blade('
            <x-sidebar title="Portal Belajar">
                <nav>
                    <a href="/dashboard">Dashboard</a>
                </nav>
            </x-sidebar>
        ');

        $sidebar->assertSee('Portal Belajar')
            ->assertSee('Dashboard');
    }

    public function test_footer_renders_correctly(): void
    {
        $footer = $this->blade('<x-footer app-name="BisaBelajar Test" />');

        $footer->assertSee('BisaBelajar Test — AI-VET Learning Platform')
            ->assertSee('Hak Cipta Dilindungi Undang-Undang');
    }

    public function test_pagination_handles_1000_pages(): void
    {
        $pagination = $this->blade('
            <x-pagination 
                :current-page="500" 
                :total-pages="1000" 
                :total-items="15000" 
                :per-page="15" 
            />
        ');

        $pagination->assertSee('1')
            ->assertSee('498')
            ->assertSee('499')
            ->assertSee('500')
            ->assertSee('501')
            ->assertSee('502')
            ->assertSee('1,000')
            ->assertSee('dari 1,000')
            ->assertSee('Menampilkan');
    }

    public function test_description_list_and_item_render_correctly(): void
    {
        $view = $this->blade('
            <x-description-list>
                <x-description-item label="Nama Lengkap" value="Budi Santoso" />
                <x-description-item label="Peran">
                    <x-badge variant="primary">TUTOR</x-badge>
                </x-description-item>
            </x-description-list>
        ');

        $view->assertSee('Nama Lengkap')
            ->assertSee('Budi Santoso')
            ->assertSee('Peran')
            ->assertSee('TUTOR');
    }

    public function test_pagination_with_laravel_length_aware_paginator(): void
    {
        $items = collect(range(1, 15));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            15000,
            15,
            500,
            ['path' => 'http://localhost/courses']
        );

        $view = $this->blade('
            <x-pagination :paginator="$paginator" />
        ', ['paginator' => $paginator]);

        $view->assertSee('500')
            ->assertSee('498')
            ->assertSee('502')
            ->assertSee('1,000')
            ->assertSee('http://localhost/courses?page=501');
    }

    public function test_back_link_component_renders_correctly(): void
    {
        $default = $this->blade('<x-back-link />');
        $default->assertSee('Kembali ke Beranda')
            ->assertSee(url('/'));

        $custom = $this->blade('<x-back-link href="/dashboard" label="Kembali ke Dashboard" :bordered="false" />');
        $custom->assertSee('Kembali ke Dashboard')
            ->assertSee('/dashboard');
    }
}
