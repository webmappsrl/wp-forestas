# CSS dei moduli Drupal Slick e Slick Lightbox

Copie senza modifiche dei CSS che il sito Drupal sardegnasentieri.it carica per la lightbox della
galleria delle pagine (formatter Blazy con `media_switch: slick_lightbox`), così che la lightbox di
WordPress sia identica (oc:8758):

| File | Da | Versione |
|---|---|---|
| `slick.module.css` | modulo `slick`, `css/layout/` (libreria `slick/base`) | 8.x-2.10 |
| `slick.theme.css` | modulo `slick`, `css/theme/` (libreria `slick/slick.theme`) | 8.x-2.10 |
| `slick.theme--default.css` | modulo `slick`, `css/theme/` (skin «default») | 8.x-2.10 |
| `slick-lightbox.module.css` | modulo `slick_lightbox`, `css/` (libreria `slick_lightbox/load`) | 8.x-1.7 |

I moduli sono distribuiti con licenza GPL-2.0-or-later, come il child theme. Le librerie JavaScript
sono in `../slick` (Slick 1.8.1, MIT) e `../slick-lightbox` (slick-lightbox 0.2.12, MIT), ognuna con la
sua licenza. Si caricano solo nelle pagine con una galleria (`inc/pagine-statiche.php`): hanno regole
globali (`.slick-prev`, `.slick-arrow`, `img[data-lazy]`…).
