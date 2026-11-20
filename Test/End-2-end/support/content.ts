/**
 * Page Builder content to seed CMS pages with, as Page Builder 2.2 saves it (the markup was taken from pages saved
 * in the admin). Page Builder reads it back onto the stage, so the specs start from a known stage without
 * dragging.
 */

/** A contained row, around its children. */
export function row(...children: string[]): string {
    return '<div data-content-type="row" data-appearance="contained" data-element="main">'
        + '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
        + ' data-background-type="image" data-video-loop="true" data-video-play-only-visible="true"'
        + ' data-video-lazy-load="true" data-video-fallback-src="" data-element="inner">'
        + children.join('')
        + '</div></div>';
}

export function heading(text: string): string {
    return `<h2 data-content-type="heading" data-appearance="default" data-element="main">${text}</h2>`;
}

export function text(html: string): string {
    return `<div data-content-type="text" data-appearance="default" data-element="main">${html}</div>`;
}

/**
 * A Columns element with two empty half-width columns, in a row, saved without layout settings (the
 * data-mageos-layout marker is written by MageOS_PageBuilderLayout on every save).
 */
export function twoColumns(): string {
    return '<style>#html-body [data-pb-style=E2EROW]{display:flex;flex-direction:column}'
        + '#html-body [data-pb-style=E2EGRP]{background-position:left top;background-size:cover;'
        + 'background-repeat:no-repeat;background-attachment:scroll;align-self:stretch}'
        + '#html-body [data-pb-style=E2ELIN]{display:flex;width:100%}'
        + '#html-body [data-pb-style=E2ECL1],#html-body [data-pb-style=E2ECL2]{justify-content:flex-start;'
        + 'display:flex;flex-direction:column;background-position:left top;background-size:cover;'
        + 'background-repeat:no-repeat;background-attachment:scroll;width:50%;align-self:stretch}</style>'
        + '<div data-content-type="row" data-appearance="contained" data-element="main">'
        + '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
        + ' data-background-type="image" data-video-loop="true" data-video-play-only-visible="true"'
        + ' data-video-lazy-load="true" data-video-fallback-src="" data-element="inner" data-pb-style="E2EROW">'
        + '<div class="pagebuilder-column-group" data-background-images="{}" data-content-type="column-group"'
        + ' data-appearance="default" data-grid-size="12" data-element="main" data-pb-style="E2EGRP">'
        + '<div class="pagebuilder-column-line" data-content-type="column-line" data-element="main" data-pb-style="E2ELIN">'
        + column('E2ECL1', 'E2E column one') + column('E2ECL2', 'E2E column two')
        + '</div></div></div></div>';
}

function column(style: string, content: string): string {
    return '<div class="pagebuilder-column" data-content-type="column" data-appearance="full-height"'
        + ` data-background-images="{}" data-element="main" data-pb-style="${style}">`
        + text(`<p>${content}</p>`)
        + '</div>';
}

export interface HeroValues {
    eyebrow?: string;
    title?: string;
    primaryText?: string;
}

/**
 * MageOS_PageBuilderComponentsExamples' Hero, as its widget directive. Plain values only: anything the directive
 * value codec escapes is entered through the form instead, which is what the specs check.
 */
export function hero(values: HeroValues = {}): string {
    const attributes: Record<string, string> = {
        image: '',
        imageAlt: '',
        highPriority: '1',
        eyebrow: values.eyebrow ?? '',
        title: values.title ?? '',
        content: '',
        primaryLink: '',
        primaryText: values.primaryText ?? '',
        secondaryLink: '',
        secondaryText: '',
        position: 'left',
        height: 'medium',
        textColor: 'dark',
        overlay: '1',
    };

    for (const value of Object.values(attributes)) {
        if (/["{}\\^$%]/.test(value)) {
            throw new Error(`hero(): "${value}" needs the directive value codec; enter it through the form.`);
        }
    }
    const directive = '{{widget type="MageOS\\\\PageBuilderComponentsExamples\\\\Block\\\\Component\\\\Hero" '
        + Object.entries(attributes).map(([name, value]) => `${name}="${value}"`).join(' ')
        + '}}';

    return `<div data-content-type="mageos_hero" data-appearance="default" data-element="main">${directive}</div>`;
}
