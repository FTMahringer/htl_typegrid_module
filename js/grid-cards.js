/**
 * @file
 * Makes HTL Grid cards clickable while preserving internal link functionality.
 */

(function (Drupal, once) {
  /**
   * Makes cards clickable while allowing links inside to work normally.
   */
  Drupal.behaviors.htlGridCards = {
    attach: function (context) {
      // Find all cards with overlay links
      const cards = once("htl-grid-card", ".htl-card", context);

      cards.forEach((card) => {
        const overlayLink = card.querySelector(".htl-card__overlay");

        if (!overlayLink) {
          return;
        }

        const overlayUrl = overlayLink.getAttribute("href");

        if (!overlayUrl) {
          return;
        }

        // Make card clickable
        card.addEventListener("click", (event) => {
          // Check if the click was on an interactive element
          const target = event.target;
          const isLink = target.tagName === "A" || target.closest("a");
          const isButton =
            target.tagName === "BUTTON" || target.closest("button");
          const isInput =
            target.tagName === "INPUT" ||
            target.tagName === "SELECT" ||
            target.tagName === "TEXTAREA";

          // If clicked on a link, button, or input, let the default action happen
          if (isLink || isButton || isInput) {
            return;
          }

          // Otherwise, navigate to the card's URL
          // Check for modifier keys (ctrl, cmd, shift) to allow opening in new tab
          if (event.ctrlKey || event.metaKey || event.shiftKey) {
            window.open(overlayUrl, "_blank");
          } else {
            window.location.href = overlayUrl;
          }
        });

        // Add visual feedback on hover
        card.addEventListener("mouseenter", () => {
          card.classList.add("htl-card--hover");
        });

        card.addEventListener("mouseleave", () => {
          card.classList.remove("htl-card--hover");
        });

        // Keyboard navigation support
        card.setAttribute("tabindex", "0");
        card.setAttribute("role", "article");

        card.addEventListener("keydown", (event) => {
          // Enter or Space key activates the card
          if (event.key === "Enter" || event.key === " ") {
            // Check if focus is on a link or button inside
            const activeElement = document.activeElement;
            const isInsideInteractive =
              activeElement.tagName === "A" ||
              activeElement.tagName === "BUTTON";

            if (!isInsideInteractive) {
              event.preventDefault();
              window.location.href = overlayUrl;
            }
          }
        });
      });
    },
  };
})(Drupal, once);
