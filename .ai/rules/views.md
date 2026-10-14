---
paths:
  - 'resources/views/**'
---

# Views

## Icons zijn Fluent UI System Icons via Flux-overrides
De app gebruikt geen Heroicons. Elk icoon in `resources/views/flux/icon/{naam}.blade.php` is een override die via de `svg()` helper van blade-icons een `fluentui-*` icoon rendert (codeat3/blade-fluentui-system-icons).

Blijf in views gewoon de Flux-namen gebruiken (`<flux:icon.plus />`, `icon="magnifying-glass"`); de override mapt naar de Fluent-naam (plus => add, magnifying-glass => search, trash => delete).

Nieuw icoon nodig? Maak het override-bestand aan naar het model van een bestaande, met de variant-mapping outline => `-24-o`, solid => `-24`, mini => `-20-o`, micro => `-16-o` (val terug op een grotere maat als die niet bestaat). Zonder override valt Flux terug op Heroicons en wijkt het icoon visueel af; `tests/Feature/FluentIconsTest.php` bewaakt dit.

`flux:icon.loading` blijft bewust de Flux-spinner.

## Attribute-bags spreaden op een component: gebruik :attributes, niet {{ $bag }}
Blade's component-tag compiler herkent binnen een componenttag alleen letterlijk `{{ $attributes }}` als spread. Een eigen bag zoals `<flux:input {{ $bind }} />` wordt NIET uitgeklapt maar als losse attribute `bind="bind"` gerenderd — de `wire:model` verdwijnt dan geruisloos en het veld bindt nergens aan (formulier lijkt gevuld, server ziet lege waarden).

Spread een zelfgebouwde bag altijd met `:attributes="$bind"`.

Dat Flux-velden geen `value=""` server-side renderen is normaal: `wire:model` wordt via Alpine's `x-model` gebonden en vult de waarde client-side. Assert in tests dus op `wire:model="..."` in de HTML, niet op `value="..."`.

## Portaalstijl: Poppins, witte panelen, effen blauw — geen gradients of glas
De hele app draait op één vlakke stijl (referentie: strak dashboard-design). Regels:

- Lettertype is Poppins (`bunny('Poppins')` in vite.config.js + `--font-sans`). Achtergrond van de pagina is `bg-canvas` (#f5f6fa), panelen zijn wit via de klasse `.sp-panel` (rounded-2xl, minimale schaduw). Gebruik geen `backdrop-blur`, geen `bg-linear-to-*`, geen gekleurde glow-schaduwen (`shadow-blue-500/30`).
- Brandkleur is `blue-600`, effen. Actieve sidebar-items zijn een full-bleed blauwe balk (zie de CSS bij `[data-flux-sidebar-item][data-current]`), primaire knoppen `bg-blue-600 hover:bg-blue-700`.
- Geen icoon in elke kaart of rij: dat oogt gegenereerd. Iconen alleen in de sidebar, in knoppen, in één lege-staat-illustratie en waar ze echt betekenis dragen (status, alert).
- Typografie: kaarttitels krijgen `.sp-card-title` (Poppins semibold 21px), de regel eronder `.sp-card-subtitle` (10px, 400). Lopende tekst en Flux-headings staan op 400 (`[data-flux-heading]`-override in app.css). Sidebar-links zijn 14px semibold, iconen 24px, rijhoogte 52px; hover en actief beslaan dezelfde full-bleed balk.
- Tabellen: geen avatars in overzichten (alleen naam), geen lijnen tussen rijen, en per rij dezelfde hover als de sidebar (`bg-zinc-100` / `white/5`). Zie de `[data-flux-cell]` / `[data-flux-row]`-regels in app.css.
- Een tabel in een kaart: `<x-table-card>` heeft geen padding meer; de inspringing zit in de cellen (`[data-flux-cell]:first-child` / `:last-child` en de pagination), zodat de rij-hover tot aan de kaartrand loopt. Kaarten met een formulier of lijst gebruiken `sp-panel p-5`.
- De laatste kolom van een tabel lijnt altijd rechts uit (centraal geregeld op `[data-flux-column]:last-child` / `[data-flux-cell]:last-child`); zet daar geen eigen `align="end"` of `text-right` meer op.
- Nadruk in tabellen: cellen zijn standaard minder belangrijk (400, `--color-ink-muted` #939393). Zet `class="sp-strong"` op de cel die er wél toe doet (500, `--color-ink` #1a1a1a, gelijk aan de kaarttitels); `sp-muted` duwt iets expliciet naar achteren. Gebruik geen `variant="strong"` meer — dat schrijft eigen zinc-kleuren.
- Rij-acties in beheertabellen: één `⋯`-dropdown (`flux:dropdown` + `flux:menu.item`) met Bewerken plus de statusactie. Aanmaken en bewerken delen één modal en één `save*()`-methode; `edit*()` vult het formulier en opent de modal via `Flux::modal('…')->show()`.
- Kerncijfers bovenaan een pagina: `<x-portal.kpi-band :items="[['label','value','countUp']]" />` — de effen blauwe balk (dashboard, clubdashboard, leddetail). Label is hoofdletters klein, waarde 3xl wit.
- Statistiekkaarten: geen icoon. Een kaart met grafiek krijgt titel (metriek) + subtitel (periode); de compacte KPI-tegels op het clubdashboard hebben alleen een subtitel ("Nieuwe leden · Afgelopen maand") boven het getal.
- Elke pagina begint met `<x-portal.hero :title :subtitle>` (acties in de `actions`-slot) en gebruikt de volledige breedte: geen `max-w-*` op de root, behalve op losstaande pagina's zoals uitnodiging accepteren.
- Bouwstenen: `<x-portal.hero>` (paginakop; met slot-inhoud wordt het een paneel, zonder slot staat de titel kaal op de canvas), `<x-portal.card :delay>`, `<x-portal.stat :count-up>`, `<x-portal.empty>`.

Animaties komen uit resources/css/app.css: utilities `animate-rise|fade|slide-in|pop|float`, plus `.stagger` (leest `style="--d: 120ms"` als animation-delay), `.sp-bar` en `.sp-ring-value` (vullen zich vanuit `--sp-width` / `--sp-dash`, dus zonder JS) en `.sp-ping`. Alpine-helpers in resources/js/app.js: `spTilt(deg)`, `spCountUp(value)`, `spFormProgress(total)` (telt `[data-sp-field]`). Een `prefers-reduced-motion`-blok zet alles uit; omzeil dat niet.

## Mix @php(...) niet met @php...@endphp in hetzelfde bestand
Blade vervangt raw PHP-blokken met een non-greedy regex op `@php ... @endphp`. Staat er ergens een inline `@php($x = 1)` vóór een blok-`@endphp` verderop in hetzelfde bestand, dan slikt de compiler alles daartussen op als PHP-code en krijg je "syntax error, unexpected token @" in de gecompileerde view.

Kies per bestand één vorm. Heeft een view een `@php ... @endphp`-blok nodig (bijv. meerdere statements in een loop), gebruik dan ook blokvorm voor de enkele toewijzingen erboven.

## Ontbrekende add-on: altijd de AddonCta, geen callout
Waar een feature achter een add-on zit die de club nog niet heeft, toon `<livewire:clubs.addon-cta :club="$club" :addon-key="AddonKey::X" wire:key="addon-cta-X" />` in plaats van een flux:callout. Het component (App\Livewire\Clubs\AddonCta) toont pitch, voordelen (AddonKey::pitch()/benefits()), maandprijs uit de Addon-rij en laat owners direct kopen via PurchaseAddon; admins zien "vraag de eigenaar". Nieuwe AddonKey? Vul pitch() en benefits() aan. Add-on wél actief maar nog niet gekoppeld (bv. Moneybird) is een andere staat: gebruik dan x-portal.empty met een koppelknop.
