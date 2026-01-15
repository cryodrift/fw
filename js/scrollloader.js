import {dom, setQueryParam, getQueryParam, hasQueryParam} from '/system.js';
import {attachListener, customEvent} from '/dataloader.js'

let isRequestInProgress = false;
let observertimeout;
let observer = {};

export function handler() {
   return async e => {
      const d = e.detail
      if (!await renderList(d.apiName, d.apiElement, d.targetElement, d.targetName, d.srcElement)) e.preventDefault()
   }
}

function getCurrentPage(name) {
   const urlParams = new URLSearchParams(window.location.search);
   return urlParams.get(name + '_page')
}

async function renderList(listName, targetEl, element, direction, nextpage) {

   const url = new URL(window.location.href);
   let pathname = url.pathname;
   pathname = pathname.endsWith('/') ? pathname : pathname + '/';
   const urlParams = new URLSearchParams(window.location.search);
   urlParams.set(listName + '_page', nextpage)

   let destinationurl = targetEl.getAttribute('data-scrollable-dest') || pathname + 'api/' + listName;

   try {
      const data = await makeRequest(destinationurl + '?' + urlParams.toString())
      console.log('scrollloader',data,destinationurl)
      if (data) {
         const child = dom(data)
         attachListener(child)

         if (direction === 'up') {
            targetEl.prepend(child);
            const pages = element.scrollHeight / element.clientHeight
            const percent = (element.scrollHeight / 100) / element.clientHeight
            element.scrollTop = (element.clientHeight / pages) * percent * 100
            observePositions(element, targetEl.querySelectorAll('.separator'), listName)
         } else {
            const lastchild = targetEl.lastElementChild
            if (lastchild.style.height) targetEl.removeChild(lastchild)
            targetEl.append(child);
            // element.scrollTop = element.scrollTop - (element.scrollHeight / 100) * 5
            observePositions(element, targetEl.querySelectorAll('.separator'), listName)
         }
         setQueryParam(listName + '_page', nextpage)
      } else {
         return false
      }
   } catch (e) {
      if (e instanceof InRequestError)
         'ignore'
      else
         console.log(e)

   }
   return true
}


function observePositions(itemList, separators, name) {

   if (observertimeout) clearTimeout(observertimeout)
   observertimeout = setTimeout(a => {
      if (observer.hasOwnProperty(name)) observer[name].disconnect()
      observer[name] = new IntersectionObserver(entries => {
         entries.forEach(entry => {
            if (entry.isIntersecting) {
               const urlParams = new URLSearchParams(window.location.search);
               const page = entry.target.getAttribute('data-scrollable-page')
               // console.log(urlParams.toString(), name, page)
               urlParams.set(name + '_page', page)
               history.replaceState(null, '', '?' + urlParams.toString());
            }
         });
      }, {
         root: itemList,
         threshold: 0.1 // Adjust threshold as needed
      });

      separators.forEach(separator => {
         observer[name].observe(separator);
      });
   }, 250)

}

async function makeRequest(url, options) {
   if (isRequestInProgress) {
      throw new InRequestError("Request is already in progress. Please wait.")
   }
   isRequestInProgress = true;
   try {
      const response = await fetch(url, options);
      if (!response.ok) {
         console.error(`HTTP error! Status: ${response.status}`);
         return
      }
      return await response.text();
   } catch (error) {
      console.error(error);
   } finally {
      isRequestInProgress = false;
   }
}

class InRequestError extends Error {
}
