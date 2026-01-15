import {dom} from '/system.js'
export function init(listElement, callback) {

   for (let s of document.styleSheets) {
      for (let r of s.cssRules) {
         for (let t of r.cssRules) {
            const parts = t.selectorText?.split(' ').shift()
            if (parts) {
               const el = dom('<li>' + parts.split('.').pop() + '</li>')
               el.firstChild.addEventListener('click', e => {
                  callback(e)
               })
               listElement.appendChild(el)
            }
         }
      }
   }
   return listElement
}
