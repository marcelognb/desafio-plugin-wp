<?php
/**
 * Plugin Name: BX LIKES
 * Version: 1.0.0
 * Author: Marcelo Gentil Noboa Sanches
 * Description: Plugin de curtidas e descurtidas para posts, com uso de cookie e ranking de posts mais curtidos.
 */
defined( 'ABSPATH' ) || exit;

/* ─────────────────────────────────────────────────────────────
 * CONSTANTES
 * ───────────────────────────────────────────────────────────── */
define( 'BXLIKES_VERSION',      '1.0.0' );
define( 'BXLIKES_DIR',          plugin_dir_path( __FILE__ ) );
define( 'BXLIKES_URL',          plugin_dir_url( __FILE__ ) );
define( 'BXLIKES_META_LIKE',    '_bxlikes_likes' );
define( 'BXLIKES_META_DIS',     '_bxlikes_dislikes' );
define( 'BXLIKES_META_VOTERS',  '_bxlikes_voters' );
define( 'BXLIKES_COOKIE',       'bxlikes_visitor' );
define( 'BXLIKES_COOKIE_TTL',   365 * DAY_IN_SECONDS );

/* ─────────────────────────────────────────────────────────────
 * ATIVAÇÃO
 * ───────────────────────────────────────────────────────────── */
register_activation_hook( __FILE__, 'bxlikes_activate' );
function bxlikes_activate(): void {
    flush_rewrite_rules();
}

/* ─────────────────────────────────────────────────────────────
 * COOKIE DO VISITANTE
 * ───────────────────────────────────────────────────────────── */
function bxlikes_get_visitor_id(): string {
    if ( ! empty( $_COOKIE[ BXLIKES_COOKIE ] ) ) {
        return sanitize_text_field( $_COOKIE[ BXLIKES_COOKIE ] );
    }

    $id = wp_generate_uuid4();

    if ( ! headers_sent() ) {
        setcookie(
            BXLIKES_COOKIE,
            $id,
            time() + BXLIKES_COOKIE_TTL,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // httponly
        );
    }

    $_COOKIE[ BXLIKES_COOKIE ] = $id;
    return $id;
}

/* ─────────────────────────────────────────────────────────────
 * HELPERS DE PONTUAÇÃO
 * ───────────────────────────────────────────────────────────── */
function bxlikes_get_counts( int $post_id ): array {
    return [
        'likes'    => (int) get_post_meta( $post_id, BXLIKES_META_LIKE, true ),
        'dislikes' => (int) get_post_meta( $post_id, BXLIKES_META_DIS,  true ),
    ];
}

function bxlikes_get_voter_state( int $post_id, string $visitor_id ): string {
    $voters = get_post_meta( $post_id, BXLIKES_META_VOTERS, true );
    if ( ! is_array( $voters ) ) return '';
    return $voters[ $visitor_id ] ?? '';
}

function bxlikes_set_voter_state( int $post_id, string $visitor_id, string $state ): void {
    $voters = get_post_meta( $post_id, BXLIKES_META_VOTERS, true );
    if ( ! is_array( $voters ) ) $voters = [];

    if ( $state === '' ) {
        unset( $voters[ $visitor_id ] );
    } else {
        $voters[ $visitor_id ] = $state;
    }

    update_post_meta( $post_id, BXLIKES_META_VOTERS, $voters );
}

/* ─────────────────────────────────────────────────────────────
 * ENDPOINT AJAX – processa voto
 * ───────────────────────────────────────────────────────────── */
add_action( 'wp_ajax_bxlikes_vote',        'bxlikes_handle_vote' );
add_action( 'wp_ajax_nopriv_bxlikes_vote', 'bxlikes_handle_vote' );

function bxlikes_handle_vote(): void {
    check_ajax_referer( 'bxlikes_nonce', 'nonce' );

    $post_id    = isset( $_POST['post_id'] ) ? (int) $_POST['post_id']          : 0;
    $action_req = isset( $_POST['vote'] )    ? sanitize_key( $_POST['vote'] )   : '';

    if ( ! $post_id || ! in_array( $action_req, [ 'like', 'dislike' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Parâmetros inválidos.' ], 400 );
    }

    if ( ! get_post( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Post não encontrado.' ], 404 );
    }

    $visitor_id   = bxlikes_get_visitor_id();
    $current_vote = bxlikes_get_voter_state( $post_id, $visitor_id );
    $counts       = bxlikes_get_counts( $post_id );

    if ( $current_vote === $action_req ) {
        // Mesmo voto → toggle off
        if ( $action_req === 'like' )    $counts['likes']    = max( 0, $counts['likes']    - 1 );
        if ( $action_req === 'dislike' ) $counts['dislikes'] = max( 0, $counts['dislikes'] - 1 );
        $new_vote = '';

    } elseif ( $current_vote !== '' ) {
        // Voto oposto → troca
        if ( $current_vote === 'like' )    $counts['likes']    = max( 0, $counts['likes']    - 1 );
        if ( $current_vote === 'dislike' ) $counts['dislikes'] = max( 0, $counts['dislikes'] - 1 );
        if ( $action_req   === 'like' )    $counts['likes']++;
        if ( $action_req   === 'dislike' ) $counts['dislikes']++;
        $new_vote = $action_req;

    } else {
        // Sem voto anterior → registra
        if ( $action_req === 'like' )    $counts['likes']++;
        if ( $action_req === 'dislike' ) $counts['dislikes']++;
        $new_vote = $action_req;
    }

    update_post_meta( $post_id, BXLIKES_META_LIKE, $counts['likes'] );
    update_post_meta( $post_id, BXLIKES_META_DIS,  $counts['dislikes'] );
    bxlikes_set_voter_state( $post_id, $visitor_id, $new_vote );

    wp_send_json_success( [
        'likes'     => $counts['likes'],
        'dislikes'  => $counts['dislikes'],
        'user_vote' => $new_vote,
    ] );
}

/* ─────────────────────────────────────────────────────────────
 * INJEÇÃO AUTOMÁTICA NOS POSTS
 * ───────────────────────────────────────────────────────────── */
add_filter( 'the_content', 'bxlikes_inject_buttons', 20 );

function bxlikes_inject_buttons( string $content ): string {
    if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $post_id    = get_the_ID();
    $visitor_id = bxlikes_get_visitor_id();
    $counts     = bxlikes_get_counts( $post_id );
    $user_vote  = bxlikes_get_voter_state( $post_id, $visitor_id );

    $like_active    = $user_vote === 'like'    ? 'wplikes-active wplikes-active--like'    : '';
    $dislike_active = $user_vote === 'dislike' ? 'wplikes-active wplikes-active--dislike' : '';

    ob_start(); ?>
    <div class="wplikes-box" data-post-id="<?php echo esc_attr( $post_id ); ?>">
        <p class="wplikes-question">Este post foi útil para você?</p>
        <div class="wplikes-buttons">

            <button class="wplikes-btn wplikes-btn--like <?php echo esc_attr( $like_active ); ?>"
                    data-vote="like"
                    aria-label="Curtir este post"
                    aria-pressed="<?php echo $user_vote === 'like' ? 'true' : 'false'; ?>">
                <span class="wplikes-icon" aria-hidden="true">👍</span>
                <span class="wplikes-label">Curtir</span>
                <span class="wplikes-count"><?php echo esc_html( $counts['likes'] ); ?></span>
            </button>

            <button class="wplikes-btn wplikes-btn--dislike <?php echo esc_attr( $dislike_active ); ?>"
                    data-vote="dislike"
                    aria-label="Não curtir este post"
                    aria-pressed="<?php echo $user_vote === 'dislike' ? 'true' : 'false'; ?>">
                <span class="wplikes-icon" aria-hidden="true">👎</span>
                <span class="wplikes-label">Não curti</span>
                <span class="wplikes-count"><?php echo esc_html( $counts['dislikes'] ); ?></span>
            </button>

        </div>
        <p class="wplikes-feedback" role="status" aria-live="polite"></p>
    </div>
    <?php
    return $content . ob_get_clean();
}

/* ─────────────────────────────────────────────────────────────
 * ENQUEUE DE ASSETS – frontend
 * ───────────────────────────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', 'bxlikes_enqueue_assets' );

function bxlikes_enqueue_assets(): void {
    if ( ! is_singular( 'post' ) ) return;

    // CSS dos botões
    wp_enqueue_style(
        'wplikes-style',
        BXLIKES_URL . 'assets/css/bxlikes-style.css',
        [],
        BXLIKES_VERSION
    );

    // JS de votação
    wp_enqueue_script(
        'wplikes-script',
        BXLIKES_URL . 'assets/js/bxlikes-script.js',
        [],
        BXLIKES_VERSION,
        true // carrega no footer
    );

    // Passa ajaxUrl e nonce para o JS
    wp_localize_script( 'wplikes-script', 'wpLikesConfig', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bxlikes_nonce' ),
    ] );
}

/* ─────────────────────────────────────────────────────────────
 * BLOCO GUTENBERG – Ranking de posts por curtidas
 * ───────────────────────────────────────────────────────────── */
add_action( 'init', 'bxlikes_register_block' );

function bxlikes_register_block(): void {
    if ( ! function_exists( 'register_block_type' ) ) return;

    // CSS do bloco (editor + frontend)
    wp_register_style(
        'wplikes-block-style',
        BXLIKES_URL . 'assets/css/bxlikes-block-style.css',
        [],
        BXLIKES_VERSION
    );

    // JS do editor Gutenberg
    wp_register_script(
        'wplikes-block-editor',
        BXLIKES_URL . 'assets/js/bxlikes-block-editor.js',
        [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
        BXLIKES_VERSION,
        true
    );

    register_block_type( 'wplikes/ranking', [
        'title'           => 'Ranking de Curtidas',
        'description'     => 'Lista os posts mais curtidos, ordenados por pontuação.',
        'category'        => 'widgets',
        'icon'            => 'heart',
        'attributes'      => [
            'numberOfPosts' => [
                'type'    => 'number',
                'default' => 5,
            ],
            'showDislikes'  => [
                'type'    => 'boolean',
                'default' => true,
            ],
            'title'         => [
                'type'    => 'string',
                'default' => 'Posts Mais Curtidos',
            ],
        ],
        'supports'        => [ 'align' => true ],
        'style'           => 'wplikes-block-style',
        'editor_script'   => 'wplikes-block-editor',
        'render_callback' => 'bxlikes_render_ranking_block',
    ] );
}

/* ─────────────────────────────────────────────────────────────
 * RENDER CALLBACK DO BLOCO – PHP server-side
 * ───────────────────────────────────────────────────────────── */
function bxlikes_render_ranking_block( array $attrs ): string {
    $num         = max( 1, min( 20, (int) ( $attrs['numberOfPosts'] ?? 5 ) ) );
    $show_dis    = (bool) ( $attrs['showDislikes'] ?? true );
    $block_title = sanitize_text_field( $attrs['title'] ?? 'Posts Mais Curtidos' );

    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $num * 4, // margem para ordenar
        'meta_query'     => [
            'relation' => 'OR',
            [ 'key' => BXLIKES_META_LIKE, 'compare' => 'EXISTS' ],
            [ 'key' => BXLIKES_META_LIKE, 'compare' => 'NOT EXISTS' ],
        ],
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ];

    $post_ids = get_posts( $args );

    // Ordena por curtidas desc, depois por (likes - dislikes) desc
    usort( $post_ids, function ( $a, $b ) {
        $la = (int) get_post_meta( $a, BXLIKES_META_LIKE, true );
        $lb = (int) get_post_meta( $b, BXLIKES_META_LIKE, true );
        if ( $la !== $lb ) return $lb - $la;
        $sa = $la - (int) get_post_meta( $a, BXLIKES_META_DIS, true );
        $sb = $lb - (int) get_post_meta( $b, BXLIKES_META_DIS, true );
        return $sb - $sa;
    } );

    $post_ids = array_slice( $post_ids, 0, $num );

    ob_start(); ?>
    <div class="wplikes-ranking">
        <div class="wplikes-ranking__header">
            <span class="wplikes-ranking__trophy" aria-hidden="true">🏆</span>
            <h3><?php echo esc_html( $block_title ); ?></h3>
        </div>
        <?php if ( empty( $post_ids ) ) : ?>
            <p class="wplikes-ranking__empty">Nenhum post com votos ainda. Seja o primeiro! 🗳️</p>
        <?php else : ?>
            <ol class="wplikes-ranking__list">
            <?php foreach ( $post_ids as $position => $pid ) :
                $likes    = (int) get_post_meta( $pid, BXLIKES_META_LIKE, true );
                $dislikes = (int) get_post_meta( $pid, BXLIKES_META_DIS,  true );
                $medals   = [ 1 => '🥇', 2 => '🥈', 3 => '🥉' ];
                $pos_num  = $position + 1;
                $pos_html = $medals[ $pos_num ] ?? $pos_num;
            ?>
                <li>
                    <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"
                       class="wplikes-ranking__item"
                       title="<?php echo esc_attr( get_the_title( $pid ) ); ?>">
                        <span class="wplikes-ranking__position" aria-label="Posição <?php echo esc_attr( $pos_num ); ?>">
                            <?php echo $pos_html; ?>
                        </span>
                        <span class="wplikes-ranking__title">
                            <?php echo esc_html( get_the_title( $pid ) ); ?>
                        </span>
                        <span class="wplikes-ranking__score">
                            <span class="wplikes-ranking__score-item" title="Curtidas">
                                👍 <strong><?php echo esc_html( $likes ); ?></strong>
                            </span>
                            <?php if ( $show_dis ) : ?>
                            <span class="wplikes-ranking__score-item" title="Não curtidas">
                                👎 <strong><?php echo esc_html( $dislikes ); ?></strong>
                            </span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}