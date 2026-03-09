(function () {
    "use strict";

    const messages = {
        like:           "Obrigado pelo seu voto! 🎉",
        dislike:        "Agradecemos seu feedback.",
        like_remove:    "Voto removido.",
        dislike_remove: "Voto removido.",
        error:          "Algo deu errado. Tente novamente.",
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
        const postId  = box.dataset.postId;
        const vote    = btn.dataset.vote;
        const allBtns = box.querySelectorAll(".wplikes-btn");

        // Loading
        allBtns.forEach(b => b.classList.add("wplikes-loading"));

        const body = new URLSearchParams({
            action:  "bxlikes_vote",
            nonce:   wpLikesConfig.nonce,
            post_id: postId,
            vote:    vote,
        });

        fetch(wpLikesConfig.ajaxUrl, {
            method:      "POST",
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
            bumpCount(vote === "like" ? likeCount : dislikeCount);

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
