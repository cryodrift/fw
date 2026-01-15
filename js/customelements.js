import {dom} from "/system.js";

/**
 * example /src/testing/ce.html
 * <style-container>
 *    <style>
 *        ::slotted(div) {
 *          color: blue;
 *       }
 *    </style>
 *    <div>
 *       blue text
 *    </div>
 * </style-container>
 *  <div id="mediafiles" class="{{g-scroll}} {{g-box-body}} g-mc1 g-mlc g-mrc" data-observe="/customelements.js|styleContainer /system.js|dataListener|document">
 *    {{comp_withforeigncsslib-and-scripts}}
 *  </div>
 */
const loadedurls = {};

export function styleContainer() {

   class StyleContainer extends HTMLElement {
      constructor() {
         super()
         const shadowRoot = this.attachShadow({mode: 'open'})
         const slot = document.createElement('slot')
         shadowRoot.appendChild(slot)
         this.slotElement = slot
      }

      styleme() {
         for (let a of this.slotElement.assignedElements()) {
            try {

               if (a.nodeName.toLowerCase() === 'template') {
                  a.content.childNodes.forEach(async el => {
                     switch (el.nodeName.toLowerCase()) {
                        case'script':
                           const url = el.getAttribute('src');
                           if (url) {
                              if (!loadedurls.hasOwnProperty(url)) {
                                 loadedurls[url] = true;
                                 await import(url).catch(console.log);
                                 console.log('styleContainer script', el, loadedurls)
                              }
                           } else {
                              this.shadowRoot.appendChild(el)
                           }
                           break;
                        case'link':
                           //TODO implement
                           const href = el.getAttribute('href');
                           this.shadowRoot.appendChild(dom('<style>@import"' + href + '";</style>'))
                           console.log('styleContainer', el)
                           break;
                        case'style':
                           console.log('styleContainer', el)
                           this.shadowRoot.appendChild(el)
                           break;
                        case'#text':
                           break;
                        case'header':
                           document.head.appendChild(dom(el.innerHTML))
                           break;
                        case'footer':
                           document.body.appendChild(dom(el.innerHTML))
                           break;
                        default:
                           // this.shadowRoot.appendChild(elem)
                           console.log('styleContainer unknown template content', el.nodeName)
                     }
                  })

               } else this.shadowRoot.appendChild(a)
            } catch (error) {
               console.log(error)
            }

         }

      }
   }

   try {
      customElements.define('style-container', StyleContainer);
   } catch (error) {

   }
   for (let el of document.getElementsByTagName('style-container')) {
      el.styleme()
   }
}
