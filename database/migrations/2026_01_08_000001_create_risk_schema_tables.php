<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================
        // trrole
        // =========================
        Schema::create('trrole', function (Blueprint $table) {
            $table->increments('i_id_role')->comment('Role ID');
            $table->string('c_role')->comment('Role Code');
            $table->string('n_role')->comment('Role Name');
            $table->text('e_role')->nullable()->comment('Role Description');
            $table->boolean('f_active')->default(true)->comment('Active Status');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->comment('Master Role');
        });

        // =========================
        // trmenu
        // =========================
        Schema::create('trmenu', function (Blueprint $table) {
            $table->increments('i_id_menu')->comment('Menu ID');
            $table->string('c_menu')->comment('Menu Code');
            $table->string('n_menu')->comment('Menu Name');
            $table->text('e_menu')->nullable()->comment('Menu Description');
            $table->boolean('f_active')->default(true)->comment('Active Status');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->comment('Master Menu');
        });

        // =========================
        // trrolemenu
        // =========================
        Schema::create('trrolemenu', function (Blueprint $table) {
            $table->increments('i_id_rolemenu')->comment('Role Menu ID');
            $table->integer('i_id_role')->comment('Role ID');
            $table->integer('i_id_menu')->comment('Menu ID');
            $table->integer('c_action')->comment('Action bitmask: CREATE=1, READ=2, UPDATE=4, DELETE=8');
            $table->boolean('f_active')->default(true)->comment('Active Status');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->unique(['i_id_role', 'i_id_menu'], 'ux_trrolemenu_role_menu');

            $table->foreign('i_id_role', 'fk_trrolemenu_role')
                ->references('i_id_role')->on('trrole');

            $table->foreign('i_id_menu', 'fk_trrolemenu_menu')
                ->references('i_id_menu')->on('trmenu');

            $table->comment('Mapping Role - Menu');
        });

        // =========================
        // truserrole
        // =========================
        Schema::create('truserrole', function (Blueprint $table) {
            $table->increments('i_id_userrole')->comment('User Role ID');
            $table->integer('i_id_user')->unique()->comment('User ID from Keycloak API');
            $table->integer('i_id_role')->comment('Role ID');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->foreign('i_id_role', 'fk_truserrole_role')
                ->references('i_id_role')->on('trrole');

            $table->comment('Mapping User - Role');
        });

        // =========================
        // trscale
        // =========================
        Schema::create('trscale', function (Blueprint $table) {
            $table->increments('i_id_scale')->comment('Scale ID');
            $table->string('c_scale_type')->comment('Scale Type: 1=Impact, 2=Likelihood');
            $table->boolean('f_scale_finance')->nullable()->comment('Scale Finance Flag');
            $table->integer('i_scale')->comment('Scale Number');
            $table->string('n_scale_assumption')->nullable()->comment('Scale Assumption');
            $table->text('v_scale')->nullable()->comment('Scale Value');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->unique(['f_scale_finance', 'i_scale'], 'ux_trscale_finance_scale');

            $table->comment('Master Scale');
        });

        // =========================
        // trscaledetail
        // =========================
        Schema::create('trscaledetail', function (Blueprint $table) {
            $table->increments('i_id_scaledetail')->comment('Scale Detail ID');
            $table->integer('i_id_scale')->comment('Scale ID');
            $table->integer('i_detail_score')->comment('Scale Detail Score');
            $table->integer('v_detail')->comment('Likelihood Operator');
            $table->integer('c_detail')->comment('Likelihood Value');
            $table->boolean('f_active')->default(true)->comment('Active Status');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->unique(['i_id_scale', 'i_detail_score'], 'ux_trscaledetail_scale_score');

            $table->foreign('i_id_scale', 'fk_trscaledetail_scale')
                ->references('i_id_scale')->on('trscale');

            $table->comment('Scale Detail');
        });

        // =========================
        // trscalemap
        // =========================
        Schema::create('trscalemap', function (Blueprint $table) {
            $table->increments('i_id_scalemap')->comment('Scale Map ID');
            $table->integer('i_id_scale_a')->comment('Scale Detail ID A');
            $table->integer('i_id_scale_b')->comment('Scale Detail ID B');
            $table->integer('i_map')->comment('Scale Map Value: A x B');
            $table->string('n_map')->comment('Scale Map Explanation');
            $table->string('c_map')->comment('Scale Map Color - In RGB');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->unique(['i_id_scale_a', 'i_id_scale_b'], 'ux_trscalemap_a_b');

            $table->foreign('i_id_scale_a', 'fk_trscalemap_scale_a')
                ->references('i_id_scaledetail')->on('trscaledetail');

            $table->foreign('i_id_scale_b', 'fk_trscalemap_scale_b')
                ->references('i_id_scaledetail')->on('trscaledetail');

            $table->comment('Scale Map (A x B)');
        });

        // =========================
        // tmtaxonomy
        // =========================
        Schema::create('tmtaxonomy', function (Blueprint $table) {
            $table->increments('i_id_taxonomy')->comment('Risk Taxonomy ID');
            $table->integer('i_id_taxonomyparent')->nullable()->comment('Parent ID');
            $table->string('c_taxonomy')->comment('Taxonomy Code');
            $table->integer('c_taxonomy_level')->comment('Taxonomy Level: 1-5');
            $table->string('n_taxonomy')->comment('Taxonomy Name');
            $table->text('e_taxonomy')->nullable()->comment('Taxonomy Description');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->foreign('i_id_taxonomyparent', 'fk_tmtaxonomy_parent')
                ->references('i_id_taxonomy')->on('tmtaxonomy');

            $table->comment('Risk Taxonomy');
        });

        // =========================
        // tmrisk
        // =========================
        Schema::create('tmrisk', function (Blueprint $table) {
            $table->increments('i_id_risk')->comment('Risk ID');
            $table->integer('i_id_taxonomy')->comment('Taxonomy ID');
            $table->string('c_risk_year', 4)->comment('Risk Year');
            $table->string('i_risk')->comment('Risk Number');
            $table->text('e_risk_event')->comment('Risk Event');
            $table->text('e_risk_cause')->comment('Risk Cause');
            $table->text('e_risk_impact')->comment('Risk Impact');
            $table->text('v_risk_impact')->comment('Risk Impact Value');
            $table->string('c_risk_impactunit')->comment('Risk Impact Unit');
            $table->boolean('f_risk_primary')->default(false)->comment('Risk Primary Flag');
            $table->text('e_kri')->nullable()->comment('Risk Key Indicator (KRI)');
            $table->string('c_kri_unit')->nullable()->comment('Risk KRI Unit');
            $table->string('c_kri_operator')->nullable()->comment('Risk KRI Operator');
            $table->integer('v_threshold_safe')->nullable()->comment('Risk Safe KRI Threshold');
            $table->integer('v_threshold_caution')->nullable()->comment('Risk Caution KRI Threshold');
            $table->integer('v_threshold_danger')->nullable()->comment('Risk Danger KRI Threshold');
            $table->string('d_exposure_period')->nullable()->comment('Risk Exposure Period');
            $table->string('c_control_effectiveness')->nullable()->comment('Risk Control Effectiveness');
            $table->string('c_org_owner')->comment('Risk Owner Organization');
            $table->string('c_org_impact')->nullable()->comment('Risk Impacted Organization');
            $table->integer('c_risk_status')->default(0)->comment('Risk Status: 0=Draft, 1=Checked By Officer, 2=Approved by Division Head, 3=Approved by GR');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->foreign('i_id_taxonomy', 'fk_tmrisk_taxonomy')
                ->references('i_id_taxonomy')->on('tmtaxonomy');

            $table->comment('Risk Master');
        });

        // =========================
        // tmriskapprove
        // =========================
        Schema::create('tmriskapprove', function (Blueprint $table) {
            $table->increments('i_id_riskapprove')->comment('Risk Approve ID');
            $table->integer('i_id_risk')->comment('Risk ID');
            $table->integer('i_id_role')->comment('Role ID');
            $table->string('i_emp')->comment('Approved By NIK');
            $table->string('n_emp')->comment('Approved By Name');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');

            $table->unique(['i_id_risk', 'i_emp'], 'ux_tmriskapprove_risk_emp');

            $table->foreign('i_id_risk', 'fk_tmriskapprove_risk')
                ->references('i_id_risk')->on('tmrisk');

            $table->foreign('i_id_role', 'fk_tmriskapprove_role')
                ->references('i_id_role')->on('trrole');

            $table->comment('Risk Approval');
        });

        // =========================
        // tmriskinherent
        // =========================
        Schema::create('tmriskinherent', function (Blueprint $table) {
            $table->increments('i_id_riskinherent')->comment('Risk Inherent ID');
            $table->integer('i_id_risk')->comment('Risk ID');
            $table->string('i_risk_inherent')->comment('Risk Inherent Number');
            $table->integer('i_id_scalemap')->comment('Scale Map ID - Inherent');
            $table->integer('v_exposure')->comment('Risk Inherent Exposure');
            $table->integer('i_id_scalemapres')->comment('Scale Map ID - Residual');
            $table->integer('v_exposure_res')->comment('Risk Residual Exposure');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->foreign('i_id_risk', 'fk_tmriskinherent_risk')
                ->references('i_id_risk')->on('tmrisk');

            $table->foreign('i_id_scalemap', 'fk_tmriskinherent_scalemap_inherent')
                ->references('i_id_scalemap')->on('trscalemap');

            $table->foreign('i_id_scalemapres', 'fk_tmriskinherent_scalemap_residual')
                ->references('i_id_scalemap')->on('trscalemap');

            $table->comment('Risk Inherent / Residual');
        });

        // =========================
        // tmriskmitigation
        // =========================
        Schema::create('tmriskmitigation', function (Blueprint $table) {
            $table->increments('i_id_riskmitigation')->comment('Risk Mitigation ID');
            $table->integer('i_id_riskinherent')->comment('Risk Inherent ID');
            $table->text('e_risk_mitigation')->comment('Risk Mitigation');
            $table->string('c_org_mitigation')->comment('Risk Mitigation Organization In Charge');
            $table->integer('v_mitigation_cost')->comment('Risk Mitigation Cost');
            $table->string('f_mitigation_month')->comment('Risk Mitigation Month (12-digit binary string map)');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->foreign('i_id_riskinherent', 'fk_tmriskmitigation_riskinherent')
                ->references('i_id_riskinherent')->on('tmriskinherent');

            $table->comment('Risk Mitigation');
        });

        // =========================
        // tmriskrealization
        // =========================
        Schema::create('tmriskrealization', function (Blueprint $table) {
            $table->increments('i_id_riskrealization')->comment('Risk Realization ID');
            $table->integer('i_id_riskinherent')->comment('Risk Inherent ID');
            $table->string('c_realization_period')->comment('Risk Realization Period');
            $table->text('e_risk_realization')->comment('Risk Realization');
            $table->integer('p_risk_realization')->comment('Risk Realization Percentage');
            $table->integer('v_realization_cost')->comment('Risk Realization Cost');
            $table->integer('i_id_scalemap')->comment('Scale Map ID');
            $table->integer('v_exposure')->comment('Risk Realization Exposure');
            $table->integer('i_entry')->comment('Created By');
            $table->timestampTz('d_entry')->useCurrent()->comment('Created At');
            $table->integer('i_update')->nullable()->comment('Updated By');
            $table->timestampTz('d_update')->nullable()->comment('Updated At');

            $table->foreign('i_id_riskinherent', 'fk_tmriskrealization_riskinherent')
                ->references('i_id_riskinherent')->on('tmriskinherent');

            $table->foreign('i_id_scalemap', 'fk_tmriskrealization_scalemap')
                ->references('i_id_scalemap')->on('trscalemap');

            $table->comment('Risk Realization');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tmriskrealization');
        Schema::dropIfExists('tmriskmitigation');
        Schema::dropIfExists('tmriskinherent');
        Schema::dropIfExists('tmriskapprove');
        Schema::dropIfExists('tmrisk');
        Schema::dropIfExists('tmtaxonomy');
        Schema::dropIfExists('trscalemap');
        Schema::dropIfExists('trscaledetail');
        Schema::dropIfExists('trscale');
        Schema::dropIfExists('truserrole');
        Schema::dropIfExists('trrolemenu');
        Schema::dropIfExists('trmenu');
        Schema::dropIfExists('trrole');
    }
};
