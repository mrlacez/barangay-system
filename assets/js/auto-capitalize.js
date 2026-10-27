(() => {
    const formSelector = "[data-auto-capitalize]";
    const fieldSelector = `${formSelector} input[type="text"], ${formSelector} textarea`;
    const sentenceStartPattern = /(^|[.!?]\s+|\n\s*)(\p{L})/gu;

    function capitalizeSentenceStarts(value) {
        return value.replace(sentenceStartPattern, (match, prefix, letter) =>
            prefix + letter.toLocaleUpperCase()
        );
    }

    document.querySelectorAll(fieldSelector).forEach((field) => {
        field.addEventListener("input", () => {
            const originalValue = field.value;
            const selectionStart = field.selectionStart;
            const selectionEnd = field.selectionEnd;
            const capitalizedValue = capitalizeSentenceStarts(originalValue);

            if (capitalizedValue === originalValue) return;

            field.value = capitalizedValue;

            if (selectionStart !== null && selectionEnd !== null) {
                field.setSelectionRange(
                    capitalizeSentenceStarts(originalValue.slice(0, selectionStart)).length,
                    capitalizeSentenceStarts(originalValue.slice(0, selectionEnd)).length
                );
            }
        });
    });
})();
