<?php
/*
Plugin Name: Generador Masivo de Cupones
Description: Plugin para generar varios cupones personalizados de forma simultánea
Version: 2.0
Author: travoltah
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: generador-masivo-de-cupones

*/

if (!defined('ABSPATH')) exit;

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

register_activation_hook(__FILE__, 'verificar_dependencias_plugin');
function verificar_dependencias_plugin() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Este plugin requiere que WooCommerce esté instalado y activado.');
    }
}

// Menú en el admin
add_action('admin_menu', function() {
    add_menu_page('Generar Cupones', 'Generar Cupones', 'manage_options', 'generar-cupones', 'formulario_generar_cupones', 'dashicons-tickets', 20);
});

// Formulario y procesamiento
function formulario_generar_cupones() {
    if (!current_user_can('manage_woocommerce')) return;
    if (!function_exists('wc_get_products')) return;

    if (isset($_POST['generar_cupones_nonce']) && wp_verify_nonce($_POST['generar_cupones_nonce'], 'generar_cupones')) {
        $cantidad = intval($_POST['cantidad_cupones']);
        $tipo_descuento = sanitize_text_field($_POST['tipo_descuento']);
        $valor_descuento = floatval($_POST['valor_descuento']);
        $envio_gratis = isset($_POST['envio_gratis']) ? 'yes' : 'no';
        $caducidad = sanitize_text_field($_POST['caducidad']);

        $productos_incluidos = array_map('intval', explode(',', sanitize_text_field($_POST['productos_incluidos'])));
        $productos_excluidos = array_map('intval', explode(',', sanitize_text_field($_POST['productos_excluidos'])));
        $categorias_incluidas = array_map('intval', explode(',', sanitize_text_field($_POST['categorias_incluidas'])));
        $categorias_excluidas = array_map('intval', explode(',', sanitize_text_field($_POST['categorias_excluidas'])));

        $emails = preg_split('/[\s,]+/', sanitize_textarea_field($_POST['emails_permitidos']), -1, PREG_SPLIT_NO_EMPTY);

        $limite_cupon = intval($_POST['limite_cupon']);
        $limite_usuario = intval($_POST['limite_usuario']);
        $gasto_minimo = floatval($_POST['gasto_minimo']);
        $gasto_maximo = floatval($_POST['gasto_maximo']);

        $cupones = generar_cupones_custom($cantidad, compact(
            'tipo_descuento','valor_descuento','envio_gratis','caducidad','productos_incluidos','productos_excluidos','categorias_incluidas','categorias_excluidas','emails','limite_cupon','limite_usuario','gasto_minimo','gasto_maximo'
        ));

        echo '<div class="notice notice-success is-dismissible"><p>Se generaron ' . esc_html($cantidad) . ' cupones.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Generar Cupones</h1>
        <form method="post">
            <?php wp_nonce_field('generar_cupones', 'generar_cupones_nonce'); ?>

            <p><label>Cantidad de cupones: <input type="number" name="cantidad_cupones" min="1" required></label></p>
            <p><label>Tipo de descuento: 
                <select name="tipo_descuento">
                    <option value="percent">Porcentaje</option>
                    <option value="fixed_cart">Fijo (carrito)</option>
                    <option value="fixed_product">Fijo (producto)</option>
                </select>
            </label></p>
            <p><label>Valor del descuento: <input type="number" step="0.01" name="valor_descuento" required></label></p>
            <p><label><input type="checkbox" name="envio_gratis"> Permitir envío gratuito</label></p>
            <p><label>Fecha de caducidad: <input type="date" name="caducidad"></label></p>
            <p><label>IDs de productos incluidos (coma): <input type="text" name="productos_incluidos"></label></p>
            <p><label>IDs de productos excluidos (coma): <input type="text" name="productos_excluidos"></label></p>
            <p><label>IDs de categorías incluidas (coma): <input type="text" name="categorias_incluidas"></label></p>
            <p><label>IDs de categorías excluidas (coma): <input type="text" name="categorias_excluidas"></label></p>
            <p><label>Emails permitidos (uno por línea o coma): <textarea name="emails_permitidos"></textarea></label></p>
            <p><label>Límite de uso por cupón: <input type="number" name="limite_cupon" min="1"></label></p>
            <p><label>Límite de uso por usuario: <input type="number" name="limite_usuario" min="1"></label></p>
            <p><label>Gasto mínimo: <input type="number" step="0.01" name="gasto_minimo"></label></p>
            <p><label>Gasto máximo: <input type="number" step="0.01" name="gasto_maximo"></label></p>

            <p><input type="submit" class="button-primary" value="Generar Cupones"></p>
        </form>
    </div>
    <?php
}

// Generar cupones personalizados
function generar_cupones_custom($cantidad, $opciones) {
    $cupones = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $codigo = strtoupper(wp_generate_password(8, false));
        $cupon_id = wp_insert_post([
            'post_title' => $codigo,
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ]);

        update_post_meta($cupon_id, 'discount_type', $opciones['tipo_descuento']);
        update_post_meta($cupon_id, 'coupon_amount', $opciones['valor_descuento']);
        update_post_meta($cupon_id, 'free_shipping', $opciones['envio_gratis']);

        if (!empty($opciones['caducidad'])) {
            update_post_meta($cupon_id, 'date_expires', strtotime($opciones['caducidad']));
        }

        update_post_meta($cupon_id, 'product_ids', implode(',', $opciones['productos_incluidos']));
        update_post_meta($cupon_id, 'exclude_product_ids', implode(',', $opciones['productos_excluidos']));
        update_post_meta($cupon_id, 'product_categories', implode(',', $opciones['categorias_incluidas']));
        update_post_meta($cupon_id, 'exclude_product_categories', implode(',', $opciones['categorias_excluidas']));
        update_post_meta($cupon_id, 'customer_email', $opciones['emails']);
        update_post_meta($cupon_id, 'usage_limit', $opciones['limite_cupon']);
        update_post_meta($cupon_id, 'usage_limit_per_user', $opciones['limite_usuario']);
        update_post_meta($cupon_id, 'minimum_amount', $opciones['gasto_minimo']);
        update_post_meta($cupon_id, 'maximum_amount', $opciones['gasto_maximo']);

        $cupones[] = $codigo;
    }
    return $cupones;
}
