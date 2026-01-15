// dataloader.js
import {remScrolled} from "/scrollable.js";
import {dom} from "/system.js";

const attachedlist = []

async function fetchAndReplace(src, targetEl, el, options, htmldest) {

   const replace = targetEl.getAttribute('data-replace')?.split('|');
   let url = src;
   if (replace && replace.length > 1) {
      url = src.replace(replace[0], replace[1])
   }
   try {
      const response = await fetch(url, options)

      if (!response.ok) {
         console.error('Network response was not ok ' + response.statusText)
         return
      }
      const child = dom(await response.text())
      await aktivateTemplate(child)
      let newEl = targetEl
      attachListener(child)
      // outer is here to be able to delete a Element without creating additional containers
      if (htmldest.toLowerCase() === 'outer') {
         newEl = Array.from(child.childNodes).find(node => node.nodeType === Node.ELEMENT_NODE);
         targetEl.parentElement.replaceChild(newEl, targetEl)
      } else {
         targetEl.innerHTML = '';
         targetEl.appendChild(child)
      }
      return newEl
   } catch (error) {
      console.error('There was a problem with the fetch operation:', error)
   } finally {

   }
}

export async function aktivateTemplate(child) {
   await templateScripts(child)
   templateLinks(child)
}

async function templateScripts(child) {
   const scripttags = child.querySelectorAll('SCRIPT')
   for (let oElem of scripttags) {
      const type = oElem.getAttribute('type')
      const src = oElem.getAttribute('src')
      let notcached = true;
      for (let item of attachedlist) {
         if (item.src && item.src === src && item._name === 'script') notcached = false
      }
      if (notcached) {
         const elem = document.createElement('script')
         if (type) elem.setAttribute('type', type)
         if (src) {
            await loadScript(src)
         } else {
            elem.textContent = oElem.textContent
            child.appendChild(elem)
         }
         attachedlist.push({src: src, _name: 'script'})
         oElem.parentNode.removeChild(oElem)
      }
   }
}

function templateLinks(child) {
   const links = child.querySelectorAll('LINK')
   links.forEach(oElem => {
      const href = oElem.getAttribute('href')
      const rel = oElem.getAttribute('rel')
      let notcached = true;
      for (let item of attachedlist) {
         if (item.href && item.href === href && item._name === 'link') notcached = false
      }
      if (notcached) {
         const elem = document.createElement('link')
         if (href) elem.setAttribute('href', href)
         if (rel) {
            elem.setAttribute('rel', rel)
            if (rel === 'stylesheet') elem.setAttribute('type', 'text/css')
         } else elem.textContent = oElem.textContent
         attachedlist.push({href: href, _name: 'link'})
         document.head.appendChild(elem)
      }
      oElem.parentNode.removeChild(oElem)
   })
}

function createShadow() {
   return document.createElement('div').attachShadow({mode: 'open'});
}

// Function to load a script and return a promise
function loadScript(src) {
   return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.type = 'text/javascript';
      script.src = src;
      script.onload = () => {
         resolve();
      };
      script.onerror = () => {
         reject(new Error(`Failed to load script: ${src}`));
      };
      document.body.appendChild(script);
   });
}

function changeActive(container, target) {
   const elems = container.getElementsByTagName(target.nodeName)
   for (let i = 0; i < elems.length; i++) {
      elems[i].classList.remove('active');
   }
   target.classList.add('active');
}

let inprogress = [];

export function attachListener(element) {
   element.querySelectorAll('[data-loader]').forEach(el => {
      const targets = el.getAttribute('data-loader').split(' ')
      const method = el.getAttribute('data-loader-method') || ''
      let evtype = 'click'
      if (el.nodeName === 'BUTTON') {
         // prevent stupid form submit events clicking the button
         el.setAttribute('type', 'button')
      }
      if (el.nodeName === 'FORM')
         evtype = 'submit'
      const procid = targets + method + evtype
      el.addEventListener(evtype, (e) => {
         // console.log('dataloader', inprogress, targets, method, el.nodeName, evtype, e.target, e)
         if (inprogress.includes(procid)) return
         inprogress.push(procid);
         e.preventDefault()
         const src = findSrc(e.target)
         let dest;
         let url;
         let href;
         let fetchOptions = {};
         if (el.nodeName === 'FORM') {
            href = src.action
            url = new URL(href, window.location.origin);
            const formdata = new FormData(el);
            if (src.method.toLowerCase() === 'post') {
               const submitter = event.submitter;
               if (submitter)
                  formdata.append(submitter.name, submitter.value)
               fetchOptions = createFetchData(formdata)
            } else {
               let urlParams = new URLSearchParams(url.search);
               for (let [key, value] of formdata.entries()) {
                  urlParams.delete(key)
                  urlParams.append(key, value)
               }
               url = new URL(url.pathname + '?' + urlParams.toString(), window.location.origin);
               href = url.pathname + url.search
               // console.log('formmode: query', urlParams.toString(), 'formData:', formdata)
            }

         } else {
            href = src.getAttribute('href') || src.getAttribute('data-loader-url')
            url = new URL(href, window.location.origin);

            if (method.toLowerCase() === 'post') {
               const formdata = new FormData();
               let urlParams = new URLSearchParams(url.search);

               for (let [key, value] of [...urlParams.entries()]) {
                  formdata.set(key, value)
                  urlParams.delete(key)
               }
               url = new URL(url.pathname, window.location.origin);

               fetchOptions = createFetchData(formdata)
               href = '';
            }
         }

         if (href) history.pushState(null, '', href);

         targets.forEach(async targetvalue => {
               const [targetid, destblock, htmldest] = targetvalue.split('|')
               let targetEl;
               if (destblock) {
                  if (destblock === 'self') targetEl = el
                  else targetEl = document.getElementById(destblock)
               } else {
                  targetEl = document.getElementById(targetid)
               }

               const eventHandled = customEvent('dataloader.start', el?.getAttribute('id'), el, targetEl, targetid, src, url, href)
               let newTargetEl
               if (targetEl && eventHandled && url.pathname !== '/null') {
                  // console.log('href', href, src, 'pathname', url.pathname, url.search)
                  let pathname = url.pathname;
                  pathname = pathname.endsWith('/') ? pathname : pathname + '/';
                  dest = pathname + 'api/' + targetid + url.search
                  newTargetEl = await fetchAndReplace(dest, targetEl, el, fetchOptions, htmldest || '')
                  remScrolled(targetid)
                  changeActive(el, src)
               } else if (eventHandled)
                  console.error('id or path missing:', targetid, destblock, htmldest, 'targetEl:', targetEl, 'pathname:', url.pathname)
               if (htmldest === 'outer' && newTargetEl) {
                  targetEl = newTargetEl
               }
               customEvent('dataloader.end', el.getAttribute('id') || targetid, el, targetEl, targetEl ? targetEl.getAttribute('id') || targetid : targetid, src, url)
               const ii = inprogress.indexOf(procid);
               if (ii !== -1) {
                  inprogress.splice(ii, 1)
               }
            }
         )
      })
   })
}

export function customEvent(name, apiName, apiElement, dataTargetElement, targetName, srcElement, url, href) {

   const event = new CustomEvent(name, {
      'detail': {
         'apiName': apiName,
         'apiElement': apiElement,
         'targetElement': dataTargetElement,
         'targetName': targetName,
         'srcElement': srcElement,
         'url': url,
         'href': href
      }, cancelable: true
   })
   return window.dispatchEvent(event)
}

function createFetchData(formData) {
   const token = document.cookie.split('; ').find(r => r.startsWith('csrftoken=')).split('=')[1];

   const authToken = 'comes from cookie in future'
   return {
      method: 'POST',
      body: formData,
      headers: {
         'Authorization': `Bearer ${authToken}`,
         "X-CSRF-Token": token
      }
   }
}

function findSrc(handler) {
   const sourcekeeper = ['data-loader-url', 'href', 'action']
   for (const a in sourcekeeper) {
      const i = sourcekeeper[a]
      const src = handler.getAttribute(i)
      if (src) return handler;
      const el = handler.closest(`[${i}]`)
      if (el) {
         handler = el
      }
   }
   return handler
}

export function eventStarted(callback) {
   window.addEventListener('dataloader.start', callback)
}

export function eventStopped(callback) {
   window.addEventListener('dataloader.end', callback)
}

export function init() {
   attachListener(document)
}
