/**
 *
 *
 */

export function getQueryParam(param) {
   const urlParams = new URLSearchParams(window.location.search)
   return urlParams.get(param) || ''
}

export function hasQueryParam(param) {
   const urlParams = new URLSearchParams(window.location.search)
   return urlParams.has(param)
}


const queryParamQueue = new Map();
let queryParamTimeout;

export function setQueryParam(param, value) {
   if (!queryParamTimeout) {
      queryParamQueue.clear();
   }
   queryParamQueue.set(param, value)
   if (queryParamTimeout) {
      clearTimeout(queryParamTimeout);
      queryParamTimeout = undefined;
   }
   queryParamTimeout = setTimeout(() => {
      queryParamTimeout = undefined;
      runSetQueryParam();
   }, 100)

}

function runSetQueryParam() {
   const urlParams = new URLSearchParams(window.location.search);

   queryParamQueue.forEach((value, param) => {
      if (value) {
         if (Array.isArray(value)) {
            urlParams.delete(param + '[]');
            value.forEach(v => urlParams.append(param + '[]', v));
         } else {
            urlParams.set(param, value);
         }
      } else {
         urlParams.delete(param);
      }
   })

   const newUrl = '?' + urlParams.toString();
   if (window.location.search !== newUrl) {
      history.replaceState(null, '', newUrl);
      window.dispatchEvent(new Event('pushstate'));
   }
}


/**
 *
 * @param node
 * @param eventhandler ==> const eventhandler = await import(handlerfile);
 * @returns {Promise<void>}
 */
export async function runDataNowHandlers(node, eventhandler) {
   // console.log('system.js', node, eventhandler)
   for (const el2 of node.querySelectorAll('[data-now-handler]')) {
      // console.log('system.js',el2)
      const commands = el2.getAttribute('data-now-handler')
      if (commands) {
         // console.log('system.js',commands)
         const commandlist = commands.split(' ')
         const e = {target: el2};
         for (let a of commandlist) {
            // console.log('system.js', eventhandler, a, el2)
            await runmodulefnk(a, eventhandler, e)
         }
      }
   }
}

/**
 * attach eventlisteners
 * @param node
 * react to event with data-eventname(ed)
 * @example <div data-handler="/eventhandler.js|click|touch /scrollhandler.js|scroll">
 *    <button data-click="functionname|param1|paramx">pressme</button>
 *    <button data-scroll="functionname|param1|paramx">pressme</button>
 *    </div>
 */
export function dataListener(node) {
   // kiss
   node.querySelectorAll('[data-handler]').forEach(async el => {
      const handlers = el.getAttribute('data-handler')
      if (handlers) {
         const handlerlist = handlers.split(' ')
         for (let handler of handlerlist) {
            const eventnames = handler.split('|')
            const handlerfile = eventnames.shift()
            const eventhandler = await import(handlerfile);
            eventnames.forEach((name) => {
               if (name === 'now') {
                  runDataNowHandlers(node, eventhandler)
               } else {
                  const eventdatakey = 'listenerevent' + name;
                  if (!el.dataset[eventdatakey]) {
                     let capture = false;
                     if (name === 'scroll') {
                        capture = true
                     }
                     el.addEventListener(name, async e => {
                        // console.log('dataListener', name, e.target)
                        if (name === 'touch') {
                           name = 'click'
                        }
                        let commands;
                        let el = e.target
                        while (el?.nodeName && el.nodeName.toLowerCase() !== 'body' && !commands) {
                           commands = el.getAttribute('data-' + name)
                           if (commands) {
                              // e.preventDefault()
                              e.parenttarget = el;
                              // REMOVED "always prevent default" moved it to each eventhandler to be flexible
                              // BUT still need this for form submit stopping, which
                              // if (name === 'submit') e.preventDefault()
                              const commandlist = commands.split(' ')
                              for (let a of commandlist) {
                                 if (!e.notconfirmed) {
                                    // console.log('dataListener event',name,commands)
                                    await runmodulefnk(a, eventhandler, e)
                                 }
                              }
                              if (!e.notconfirmed) {
                                 commands = '';
                              }
                           }
                           if (!commands && el.nodeName.toLowerCase() !== 'body') {
                              el = el.parentElement;
                           }
                        }
                        // console.log('data-handler', name, e.notconfirmed)
                     }, capture)
                     el.dataset[eventdatakey] = "true";
                  }
               }
            })
         }
      }
   })
}

/**
 * use <div data-observe="/scriptfile.js|method|param|..s">
 * @param node @type {Element}
 */
export async function dataObserver(node) {

   function dada(el) {
      const commands = el.getAttribute('data-observe')?.split(' ')
      if (commands) {
         commands.forEach(async command => {
            const parts = command.split('|')
            const handlerfile = parts.shift()
            const eventhandler = await import(handlerfile);
            await runmodulefnk(parts.join('|'), eventhandler, el)
         })
      }

   }

   function attach(nodelist) {
      nodelist.forEach(elem => {
         if (elem.nodeType === 1) {
            dada(elem)
            elem.querySelectorAll('[data-observe]').forEach(el => {
               dada(el)
            })
         }
      })
   }

   attach(node.querySelectorAll('[data-observe]'))

   return new MutationObserver((ml, observer) => {
      ml.forEach(m => {
         if (m.type === 'childList') {
            attach(m.addedNodes)
         }
      })
   }).observe(node, {attributes: true, childList: true, subtree: true})

}

export async function runmodulefnk(commandline, eventhandler, firstparam) {
   const params = commandline.split('|')
   const name = params.shift()
   const handler = eventhandler[name] ? eventhandler[name] : undefined
   if (handler) {
      await handler(firstparam, ...params);
   }
   // console.log('runmodule',commandline,firstparam,handlerfile)
}

/**
 * @param node @type {Element}
 */
export function dataqueryclick(node) {
   node.querySelectorAll('[data-queryclick]').forEach(el => {
      const [name, value] = el.getAttribute('data-queryclick').split('|')
      // console.log('dataqueryclick',name, value,el)
      let query = getQueryParam(name)
      let values = []
      if (query) {
         values = query.split('|')
         values.forEach(val => {
            if (val === value) {
               el.click()
               // console.log('data-queryclick', name, value, el)
            }
         })
      }
   })
}


/**
 * https://developer.mozilla.org/en-US/docs/Web/API/DocumentFragment
 * append or insert the fragment into the DOM using Node interface methods
 * such as appendChild() or insertBefore().
 * Doing this MOVES the fragment's nodes into the DOM,
 * leaving behind an EMPTY DocumentFragment.
 * @param htmlstring
 * @returns Node
 * @constructor
 */
export function dom(htmlstring) {
   let template = document.createElement('template');
   template.innerHTML = htmlstring;
   return template.content;
}

export function iterateGetElements(coll, callback) {
   if (Array.isArray(coll) || coll instanceof NodeList) {
      coll.forEach(el => iterateGetElements(el, callback))
   } else {
      callback(coll)
   }
}

/**
 * universal selector helper
 * space character is double backslash \\
 * @param e
 * @param selector
 * @returns {*}
 */
export function getElements(e, selector) {
   let elems = [[]];
   let parent = document;
   let target = e.parenttarget || e.target;
   if (e?.target?.slotElement) {
      parent = e.target.shadowRoot
      target = e.target.shadowRoot.activeElement
   }

   selector = selector.replace(/\\\\/g, ' ');
   const specialselectors = [
      'collection',
      'self',
      'selfattr',
      'form',
      'parent',
      'name',
      'next',
      'prev',
      'nextparent',
      'prevparent',
      'sibling',
   ];


   const select = (selector) => {
      let elems;
      switch (selector) {
         case'collection':
            elems = e.collection
            break;
         case'self':
            elems = target
            break;
         case'selfattr':
            elems = [Array.from(target.attributes).reduce((obj, attr) => {
               obj[attr.name] = attr.value;
               return obj;
            }, {})]
            break;
         case'form':
            elems = target.closest('form')
            break;
         case'parent':
            elems = target.parentElement
            break;
         case'name':
            //TODO das ist mist
            elems = parent.getElementById(target.getAttribute('name'))
            break;
         case'next':
            elems = target.nextElementSibling
            break;
         case'prev':
            elems = target.previousElementSibling
            break;
         case'nextparent':
            elems = target.parentNode.nextElementSibling
            break;
         case'prevparent':
            elems = target.parentNode.previousElementSibling
            break;
         case'sibling':
            elems = [Array.from(target.parentNode.children).filter(child => child !== target)]
            break;
         default:
            const first = selector.split(' ').shift()
            if (specialselectors.includes(first)) {
               elems = select(first)
               // console.log('getElements.useFirst', first, elems)
               let out = []
               iterateGetElements(elems, el => {
                  out.push(el.querySelectorAll(selector.split(' ').slice(1).join(' ')))
               })
               elems = out;
            } else {
               elems = parent.querySelectorAll(selector)
            }

      }
      return elems;
   }
   elems = select(selector)
   // console.log('getElements', elems)
   if (elems instanceof NodeList) {
      elems = [[...elems]]
      // iterateGetElements(elems,console.log)
   } else if (elems instanceof Node) {
      elems = [[elems]]
      // iterateGetElements(elems,console.log)
   } else if (typeof elems) {
      if (elems) {
         // console.log('getElements spezial Selector:', selector, elems, typeof elems)
         // iterateGetElements(elems, (i, k) => console.log('', i, k))
         // throw new Error('Implement this!')
      }
   }
   if (!elems) {
      elems = [[]];
   }
   // console.log('getElements', elems)
   return elems;
}

// pro Callback eigene Debounce-Instanz
const debounceMap = new WeakMap();

export function debounce(params, keyobj, fn, speed = 250) {
   let inst = debounceMap.get(keyobj);
   if (!inst) {
      inst = createDebounce(fn, speed);
      debounceMap.set(keyobj, inst);
   }
   return inst(params);
}

function createDebounce(fn, speed) {
   let t;
   let queued = false;
   let busy = false;
   let lastParamsArr = [];

   return function (params) {
      let paramsarr = params;

      if (!params || typeof params[Symbol.iterator] !== 'function') {
         paramsarr = Object.values(params);
      }

      lastParamsArr = paramsarr;

      if (busy) {
         queued = true;
         return;
      }

      clearTimeout(t);
      t = setTimeout(async () => {
         busy = true;
         await fn(...lastParamsArr);
         busy = false;

         if (queued) {
            queued = false;
            // wieder über dieselbe Instanz → gleicher State
            return createCall();
         }
      }, speed);
   };

   function createCall() {
      return fn(...lastParamsArr);
   }
}
