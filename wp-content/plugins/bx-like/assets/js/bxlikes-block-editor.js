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
                            checked:  showDislikes,
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
