<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_archetypes', function (Blueprint $table) {
            $table->text('base_prompt')->nullable()->after('target_audience');
        });

        $this->seedBasePrompt();
    }

    public function down(): void
    {
        Schema::table('aero_sites_archetypes', function (Blueprint $table) {
            $table->dropColumn('base_prompt');
        });
    }

    protected function seedBasePrompt(): void
    {
        \Db::table('aero_sites_archetypes')
            ->where('handle', 'generic-estandar')
            ->update([
                'base_prompt' => trim('
[Nombre del negocio] es [tipo de negocio/rubro] con sede en [ciudad/zona]. '
. 'Llevamos [X años] en el mercado y ofrecemos [servicios o productos principales]. '
. 'Nuestros clientes nos eligen por [diferenciador: precio, calidad, atención personalizada, '
. 'rapidez, tecnología, garantía, etc.]. Atendemos principalmente a [tipo de cliente objetivo]. '
. 'Contamos con clientes satisfechos que pueden dar testimonio de nuestro trabajo. '
. 'Buscamos transmitir una imagen profesional y confiable, sin sonar genéricos ni exagerar '
. 'promesas que no podemos cumplir.'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
};
