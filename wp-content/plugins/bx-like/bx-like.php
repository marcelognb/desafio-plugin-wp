<?php
/**
 * Plugin Name: BX LIKES
 * Version: 1.0.0
 * Author: Marcelo Gentil Noboa Sanches
 * Description: Plugin de curtidas e descurtidas para posts, com uso de cookie e ranking de posts mais curtidos.
 */
defined('ABSPATH') || exit;

/* CONSTANTES */
define('BXLIKES_VERSION','1.0.0'); 
define('BXLIKES_META_LIKE','_bxlikes_likes');
define('BXLIKES_META_DIS','_bxlikes_dislikes');
define('BXLIKES_META_VOTERS','_bxlikes_voters');
define('BXLIKES_COOKIE','bxlikes_visitor');
define('BXLIKES_COOKIE_TTL', 365 * DAY_IN_SECONDS );


/* 2. ATIVAÇÃO - garante meta inicializado (sem tabela extra; usa post_meta) */
register_activation_hook( __FILE__,'bxlikes_activate');
function bxlikes_activate() {
    flush_rewrite_rules();
}

/* 3. COOKIE DO VISITANTE */
/**
 * Retorna (e cria se necessário) um ID único por visitante via cookie.
 */
function bxlikes_get_visitor_id(): string {
    if ( ! empty( $_COOKIE[ BXLIKES_COOKIE ] ) ) {
        return sanitize_text_field( $_COOKIE[ BXLIKES_COOKIE ] );
    }

    $id = wp_generate_uuid4();
    // Seta via header - funciona antes de qualquer output
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

/* HELPERS DE PONTUAÇÃO */
function bxlikes_get_counts( int $post_id ): array {
    return [
        'likes'    => (int) get_post_meta( $post_id, BXLIKES_META_LIKE, true ),
        'dislikes' => (int) get_post_meta( $post_id, BXLIKES_META_DIS,    true ),
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
    if ( $state === '') {
        unset( $voters[ $visitor_id ] );
    } else {
        $voters[ $visitor_id ] = $state;
    }
    update_post_meta( $post_id, BXLIKES_META_VOTERS, $voters );
}

/* ENDPOINT AJAX - processa voto */
add_action('wp_ajax_bxlikes_vote',        'bxlikes_handle_vote');
add_action('wp_ajax_nopriv_bxlikes_vote','bxlikes_handle_vote');

function bxlikes_handle_vote(): void {
    check_ajax_referer('bxlikes_nonce','nonce');

    $post_id    = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
    $action_req = isset( $_POST['vote'] )    ? sanitize_key( $_POST['vote'] ) : '';

    if ( ! $post_id || ! in_array( $action_req, [ 'like','dislike' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Parâmetros inválidos.' ], 400 );
    }

    if ( ! get_post( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Post não encontrado.' ], 404 );
    }

    $visitor_id = bxlikes_get_visitor_id();
    $current_vote = bxlikes_get_voter_state( $post_id, $visitor_id );
    $counts     = bxlikes_get_counts( $post_id );

    // --- Lógica de toggle e troca de voto ---
    if ( $current_vote === $action_req ) {
        // Mesmo voto → remove (toggle off)
        if ( $action_req === 'like')    $counts['likes']    = max( 0, $counts['likes']    - 1 );
        if ( $action_req === 'dislike') $counts['dislikes'] = max( 0, $counts['dislikes'] - 1 );
        $new_vote = '';
    } elseif ( $current_vote !== '') {
        // Voto oposto → troca
        if ( $current_vote === 'like')    $counts['likes']    = max( 0, $counts['likes']    - 1 );
        if ( $current_vote === 'dislike') $counts['dislikes'] = max( 0, $counts['dislikes'] - 1 );
        if ( $action_req    === 'like')     $counts['likes']++;
        if ( $action_req    === 'dislike')    $counts['dislikes']++;
        $new_vote = $action_req;
    } else {
        // Sem voto anterior → registra
        if ( $action_req === 'like')    $counts['likes']++;
        if ( $action_req === 'dislike') $counts['dislikes']++;
        $new_vote = $action_req;
    }

    // Persiste
    update_post_meta( $post_id, BXLIKES_META_LIKE, $counts['likes'] );
    update_post_meta( $post_id, BXLIKES_META_DIS,    $counts['dislikes'] );
    bxlikes_set_voter_state( $post_id, $visitor_id, $new_vote );

    wp_send_json_success( [
        'likes'    => $counts['likes'],
        'dislikes' => $counts['dislikes'],
        'user_vote'    => $new_vote,
    ] );
}

/* INJEÇÃO AUTOMÁTICA NOS POSTS - hook the_content  */
add_filter('the_content','bxlikes_inject_buttons', 20 );

function bxlikes_inject_buttons( string $content ): string {
    if ( ! is_singular('post') || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $post_id    = get_the_ID();
    $visitor_id = bxlikes_get_visitor_id();
    $counts     = bxlikes_get_counts( $post_id );
    $user_vote    = bxlikes_get_voter_state( $post_id, $visitor_id );

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

/* CSS + JS no frontend */
add_action('wp_enqueue_scripts','bxlikes_enqueue_assets');

function bxlikes_enqueue_assets(): void {
    if ( ! is_singular('post') ) return;

    // ── CSS ──────────────────────────────────────────────────────────────────
    $css = '
    .wplikes-box {
        margin: 2.5rem 0 1.5rem;
        padding: 1.5rem 2rem;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: linear-gradient(135deg, #f8fafc 0%, #f0f4ff 100%);
        text-align: center;
        font-family: inherit;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
    }
    .wplikes-question {
        margin: 0 0 1.2rem;
        font-size: .95rem;
        font-weight: 600;
        color: #475569;
        letter-spacing: .01em;
        text-transform: uppercase;
    }
    .wplikes-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }
    .wplikes-btn {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .65rem 1.4rem;
        border: 2px solid #cbd5e1;
        border-radius: 50px;
        background: #fff;
        color: #475569;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s ease;
        user-select: none;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .wplikes-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,.12);
    }
    .wplikes-btn:focus-visible {
        outline: 3px solid #6366f1;
        outline-offset: 2px;
    }
    .wplikes-btn--like:hover {
        border-color: #22c55e;
        color: #16a34a;
        background: #f0fdf4;
    }
    .wplikes-btn--dislike:hover {
        border-color: #f87171;
        color: #dc2626;
        background: #fef2f2;
    }
    /* Estado ativo - like */
    .wplikes-btn.wplikes-active--like {
        border-color: #22c55e;
        background: #dcfce7;
        color: #15803d;
        box-shadow: 0 0 0 3px rgba(34,197,94,.2);
    }
    /* Estado ativo - dislike */
    .wplikes-btn.wplikes-active--dislike {
        border-color: #f87171;
        background: #fee2e2;
        color: #b91c1c;
        box-shadow: 0 0 0 3px rgba(248,113,113,.2);
    }
    .wplikes-icon { font-size: 1.2rem; line-height: 1; }
    .wplikes-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 0 6px;
        border-radius: 20px;
        background: rgba(0,0,0,.06);
        font-size: .8rem;
        font-weight: 700;
        transition: transform .15s ease;
    }
    .wplikes-count--bump { transform: scale(1.4); }
    .wplikes-feedback {
        margin: .9rem 0 0;
        min-height: 1.2em;
        font-size: .85rem;
        color: #64748b;
        font-style: italic;
        transition: opacity .3s;
    }
    /* Loading state */
    .wplikes-btn.wplikes-loading {
        opacity: .6;
        pointer-events: none;
    }
    .wplikes-btn.wplikes-loading .wplikes-icon::after {
        content: "";
        display: inline-block;
        width: 14px; height: 14px;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: wplikes-spin .6s linear infinite;
        vertical-align: middle;
        margin-left: 4px;
    }
    @keyframes wplikes-spin { to { transform: rotate(360deg); } }
    ';

    wp_register_style('wplikes-style', false );
    wp_enqueue_style('wplikes-style');
    wp_add_inline_style('wplikes-style', $css );

    // ── JS ───────────────────────────────────────────────────────────────────
    wp_register_script('wplikes-script', false, [], BXLIKES_VERSION, true );
    wp_enqueue_script('wplikes-script');
    wp_localize_script('wplikes-script','wpLikesConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bxlikes_nonce'),
    ] );

    $js = '
    (function () {
        "use strict";

        const messages = {
        like:        "Obrigado pelo seu voto! 🎉",
        dislike:     "Agradecemos seu feedback.",
        like_remove: "Voto removido.",
        dislike_remove: "Voto removido.",
        error:     "Algo deu errado. Tente novamente.",
        };

        function bumpCount(el) {
        el.classList.remove("wplikes-count--bump");
        void el.offsetWidth; // reflow
        el.classList.add("wplikes-count--bump");
        setTimeout(() => el.classList.remove("wplikes-count--bump"), 250);
        }

        function setFeedback(box, msg) {
        const p = box.querySelector(".wplikes-feedback");
        if (!p) return;
        p.textContent = msg;
        clearTimeout(box._feedbackTimer);
        box._feedbackTimer = setTimeout(() => { p.textContent = ""; }, 3000);
        }

        function handleVote(btn, box) {
        const postId    = box.dataset.postId;
        const vote    = btn.dataset.vote;
        const allBtns = box.querySelectorAll(".wplikes-btn");

        // Loading
        allBtns.forEach(b => b.classList.add("wplikes-loading"));

        const body = new URLSearchParams({
            action:    "bxlikes_vote",
            nonce: wpLikesConfig.nonce,
            post_id: postId,
            vote:    vote,
        });

        fetch(wpLikesConfig.ajaxUrl, {
            method:    "POST",
            credentials: "same-origin",
            headers:     { "Content-Type": "application/x-www-form-urlencoded" },
            body:        body.toString(),
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.data?.message || "Erro desconhecido");

            const { likes, dislikes, user_vote } = res.data;

            // Atualiza contadores
            const likeBtn    = box.querySelector(".wplikes-btn--like");
            const dislikeBtn = box.querySelector(".wplikes-btn--dislike");

            const likeCount    = likeBtn.querySelector(".wplikes-count");
            const dislikeCount = dislikeBtn.querySelector(".wplikes-count");

            likeCount.textContent    = likes;
            dislikeCount.textContent = dislikes;
            bumpCount(vote === "like"    ? likeCount    : dislikeCount);

            // Atualiza estados visuais
            likeBtn.classList.remove("wplikes-active", "wplikes-active--like");
            dislikeBtn.classList.remove("wplikes-active", "wplikes-active--dislike");
            likeBtn.setAttribute("aria-pressed", "false");
            dislikeBtn.setAttribute("aria-pressed", "false");

            if (user_vote === "like") {
                likeBtn.classList.add("wplikes-active", "wplikes-active--like");
                likeBtn.setAttribute("aria-pressed", "true");
            } else if (user_vote === "dislike") {
                dislikeBtn.classList.add("wplikes-active", "wplikes-active--dislike");
                dislikeBtn.setAttribute("aria-pressed", "true");
            }

            // Feedback
            const msgKey = user_vote === "" ? vote + "_remove" : vote;
            setFeedback(box, messages[msgKey] || "");
        })
        .catch(() => setFeedback(box, messages.error))
        .finally(() => allBtns.forEach(b => b.classList.remove("wplikes-loading")));
        }

        // Event delegation - suporta múltiplos boxes na página
        document.addEventListener("click", function (e) {
        const btn = e.target.closest(".wplikes-btn");
        if (!btn) return;
        const box = btn.closest(".wplikes-box");
        if (!box) return;
        handleVote(btn, box);
        });
    })();
    ';

    wp_add_inline_script('wplikes-script', $js );
}

/* BLOCO GUTENBERG - Ranking de posts por curtidas */
add_action('init','bxlikes_register_block');

function bxlikes_register_block(): void {
    if ( ! function_exists('register_block_type') ) return;

    // CSS do bloco (editor + frontend)
    $block_css = '
    .wplikes-ranking {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        font-family: inherit;
        box-shadow: 0 2px 12px rgba(0,0,0,.07);
    }
    .wplikes-ranking__header {
        padding: 1rem 1.5rem;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff;
        display: flex;
        align-items: center;
        gap: .6rem;
    }
    .wplikes-ranking__header h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: .02em;
    }
    .wplikes-ranking__trophy { font-size: 1.3rem; }
    .wplikes-ranking__list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .wplikes-ranking__item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: .85rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background .15s;
        text-decoration: none;
        color: inherit;
    }
    .wplikes-ranking__item:last-child { border-bottom: none; }
    .wplikes-ranking__item:hover { background: #f8faff; }
    .wplikes-ranking__position {
        flex-shrink: 0;
        width: 28px; height: 28px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #64748b;
        font-size: .75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .wplikes-ranking__item:nth-child(1) .wplikes-ranking__position { background: #fef08a; color: #854d0e; }
    .wplikes-ranking__item:nth-child(2) .wplikes-ranking__position { background: #e2e8f0; color: #475569; }
    .wplikes-ranking__item:nth-child(3) .wplikes-ranking__position { background: #fed7aa; color: #9a3412; }
    .wplikes-ranking__title {
        flex: 1;
        font-size: .92rem;
        font-weight: 600;
        color: #1e293b;
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wplikes-ranking__title:hover { color: #6366f1; }
    .wplikes-ranking__score {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: .6rem;
        font-size: .8rem;
        color: #64748b;
    }
    .wplikes-ranking__score-item {
        display: flex;
        align-items: center;
        gap: .25rem;
    }
    .wplikes-ranking__empty {
        padding: 2rem;
        text-align: center;
        color: #94a3b8;
        font-size: .9rem;
    }
    ';

    wp_register_style('wplikes-block-style', false );
    wp_enqueue_block_style('wplikes/ranking', [ 'handle' => 'wplikes-block-style' ] );
    wp_add_inline_style('wplikes-block-style', $block_css );

    register_block_type('wplikes/ranking', [
        'title'         => 'Ranking de Curtidas',
        'description'     => 'Lista os posts mais curtidos, ordenados por pontuação.',
        'category'        => 'widgets',
        'icon'        => 'heart',
        'attributes'    => [
        'numberOfPosts' => [
            'type'    => 'number',
            'default' => 5,
        ],
        'showDislikes' => [
            'type'    => 'boolean',
            'default' => true,
        ],
        'title' => [
            'type'    => 'string',
            'default' => 'Posts Mais Curtidos',
        ],
        ],
        'supports'        => [ 'align' => true ],
        'render_callback' => 'bxlikes_render_ranking_block',
        // Editor script inline abaixo
        'editor_script' => 'wplikes-block-editor',
    ] );

    // Editor script inline
    $editor_js = '
    (function (blocks, element, blockEditor, components, i18n) {
        const { registerBlockType } = blocks;
        const { createElement: el, Fragment } = element;
        const { InspectorControls, useBlockProps } = blockEditor;
        const { PanelBody, RangeControl, ToggleControl, TextControl } = components;
        const { __ } = i18n;

        registerBlockType("wplikes/ranking", {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { numberOfPosts, showDislikes, title } = attributes;
            const blockProps = useBlockProps({ className: "wplikes-ranking-editor" });

            return el(
                Fragment,
                null,
                el(
                InspectorControls,
                null,
                el(
                    PanelBody,
                    { title: __("Configurações do Ranking", "wp-likes"), initialOpen: true },
                    el(TextControl, {
                        label:    __("Título do Bloco", "wp-likes"),
                        value:    title,
                        onChange: v => setAttributes({ title: v }),
                    }),
                    el(RangeControl, {
                        label:    __("Número de Posts", "wp-likes"),
                        value:    numberOfPosts,
                        onChange: v => setAttributes({ numberOfPosts: v }),
                        min: 1,
                        max: 20,
                    }),
                    el(ToggleControl, {
                        label:    __("Exibir Dislikes", "wp-likes"),
                        checked:    showDislikes,
                        onChange: v => setAttributes({ showDislikes: v }),
                    })
                )
                ),
                el(
                "div",
                blockProps,
                el(
                    "div",
                    { className: "wplikes-ranking" },
                    el(
                        "div",
                        { className: "wplikes-ranking__header" },
                        el("span", { className: "wplikes-ranking__trophy" }, "🏆"),
                        el("h3", null, title || __("Posts Mais Curtidos", "wp-likes"))
                    ),
                    el(
                        "p",
                        { style: { padding: "1rem 1.5rem", margin: 0, color: "#64748b", fontSize: ".9rem" } },
                        __("O ranking será exibido com os ", "wp-likes") + numberOfPosts + __(" posts mais curtidos.", "wp-likes")
                    )
                )
                )
            );
        },
        save: function () {
            // Renderizado pelo PHP
            return null;
        }
        });
    }(
        window.wp.blocks,
        window.wp.element,
        window.wp.blockEditor,
        window.wp.components,
        window.wp.i18n
    ));
    ';

    // ── Script do editor (registro do bloco JS) ───────────────────────────
    add_action( 'enqueue_block_editor_assets', function() use ( $editor_js ) {
    wp_register_script(
        'wplikes-block-editor',
        plugins_url( '', __FILE__ ),
        [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
        BXLIKES_VERSION,
        true
    );
    wp_add_inline_script( 'wplikes-block-editor', $editor_js );
} );

}

/* RENDER CALLBACK DO BLOCO - PHP server-side */
function bxlikes_render_ranking_block( array $attrs ): string {
    $num = max( 1, min( 20, (int) ( $attrs['numberOfPosts'] ?? 5 ) ) );
    $show_dis = (bool) ( $attrs['showDislikes'] ?? true );
    $block_title = sanitize_text_field( $attrs['title'] ?? 'Posts Mais Curtidos');

    // Busca todos os posts publicados com meta de likes
    $args = [
        'post_type'    => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $num * 4, // margem para ordenar
        'meta_query'     => [
        'relation' => 'OR',
        [
            'key'     => BXLIKES_META_LIKE,
            'compare' => 'EXISTS',
        ],
        [
            'key'     => BXLIKES_META_LIKE,
            'compare' => 'NOT EXISTS',
        ],
        ],
        'no_found_rows'    => true,
        'fields'     => 'ids',
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

    ob_start();
    ?>
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
            $dislikes = (int) get_post_meta( $pid, BXLIKES_META_DIS,    true );
            $medals = [ 1 => '🥇', 2 => '🥈', 3 => '🥉' ];
            $pos_num    = $position + 1;
            $pos_html = isset( $medals[ $pos_num ] ) ? $medals[ $pos_num ] : $pos_num;
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