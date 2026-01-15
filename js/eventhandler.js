/**
 * all exported functions can be used in data-click
 */
import {getElements, dataListener, setQueryParam, getQueryParam, dom, iterateGetElements} from '/system.js';
import {getQuery, setParamOnUrls} from "/modifyurls.js";

let hideclass = ['g-dh']

// small private helper
function getCookie(name) {
   const all = document?.cookie || ''
   const hit = all.split('; ').find(r => r.startsWith(name + '='))
   return hit ? hit.split('=')[1] : undefined
}

/**
 * set mobile mode for methods
 * mainly use gs-dh insteadof g-dh
 * @param e
 */
export function mobile(e) {
   pd(e);
   hideclass = ['gs-dh']
}

/**
 * reset mobile
 * @param e
 */
export function mobileoff(e) {
   pd(e);
   hideclass = ['g-dh']
}

/**
 * @deprecated because it uses id instead of selector
 */
export function toggle(e, targetId) {
   pd(e);
   const target = e.parenttarget || e.target;
   const el = document.getElementById(targetId)
   const name = el.getAttribute('data-name')
   el.classList.toggle('g-dh')
   if (el.classList.contains('g-dh')) {
      if (name) {
         target.innerText = name
      }
      classes(target)
   } else {
      classes(target, true)
   }
}

/**
 * @deprecated because it uses id instead of selector
 */
export function togglenext(e, targetId) {
   pd(e);
   const target = e.parenttarget || e.target;
   let el = target
   console.log('togglenext', el, el.nextElementSibling.classList)
   if (targetId) {
      el = document.getElementById(targetId)
   }
   hideclass.forEach((classname => el.nextElementSibling.classList.toggle(classname)))
   if (el.nextElementSibling.classList.contains('g-dh')) {
      classes(target)
      classes(el.nextElementSibling)
   } else {
      classes(target, true)
   }
   classes(el.nextElementSibling, true)
}

/**
 * @deprecated because it uses id instead of selector
 */
export function toggleprev(e, targetId) {
   pd(e);
   const target = e.parenttarget || e.target;
   let el = target
   if (targetId) {
      el = document.getElementById(targetId)
   }

   // Find previous visible sibling (skip those currently hidden by any hideclass)
   let prev = el.previousElementSibling
   while (prev && hideclass.some(cls => prev.classList.contains(cls))) {
      prev = prev.previousElementSibling
   }
   if (!prev) {
      return
   }

   hideclass.forEach(classname => prev.classList.toggle(classname))
   if (prev.classList.contains('g-dh')) {
      classes(target)
      classes(prev)
   } else {
      classes(target, true)
   }
   classes(prev, true)
}

/**
 * @deprecated because it uses id instead of selector
 */
export function togglenextparent(e, targetId) {
   pd(e);
   const target = e.parenttarget || e.target;
   let el = target
   if (targetId) {
      el = document.getElementById(targetId)
   }
   el.parentElement.nextElementSibling.classList.toggle('g-dh')
   if (el.parentElement.nextElementSibling.classList.contains('g-dh')) {
      classes(target)
   } else {
      classes(target, true)
   }

}

/**
 * hide x elements
 */
export function hide(e, ...selectors) {
   pd(e);
   const target = e.parenttarget || e.target;
   if (selectors.length) {
      selectors.forEach(selector => {
         const coll = getElements(e, selector)
         coll.forEach(elems => {
            elems.forEach(el => {
               hideclass.forEach((classname => el.classList.add(classname)))
            })
         })
      })
   } else {
      hideclass.forEach((classname => target.classList.add(classname)))
   }
}

/**
 * show x elements
 */
export function show(e, ...selectors) {
   pd(e);
   const target = e.parenttarget || e.target;
   if (selectors.length) {
      selectors.forEach(selector => {
         const coll = getElements(e, selector)
         coll.forEach(elems => {
            elems.forEach(el => {
               hideclass.forEach((classname => el.classList.remove(classname)))
            })
         })
      })
   } else {
      hideclass.forEach((classname => target.classList.remove(classname)))
   }
}

export function hideparent(e, ...selectors) {
   pd(e);
   const target = e.parenttarget || e.target;
   if (selectors.length) {
      selectors.forEach(selector => {
         const coll = getElements(e, selector)
         coll.forEach(elems => {
            elems.forEach(el => {
               hideclass.forEach((classname => el.parentElement.classList.add(classname)))
            })
         })
      })
   } else {
      hideclass.forEach((classname => target.parentElement.classList.add(classname)))
   }
}

export function showparent(e, ...selectors) {
   pd(e);
   if (selectors.length) {
      selectors.forEach(selector => {
         const coll = getElements(e, selector)
         coll.forEach(elems => {
            elems.forEach(el => {
               hideclass.forEach((classname => el.parentElement.classList.remove(classname)))
            })
         })
      })
   } else {
      console.log('eventhandler.js.showparent needs at least one selector')
   }
}


/**
 * @deprecated because it uses id instead of selector
 */
export function shownext(e, targetId) {
   pd(e);
   const target = e.parenttarget || e.target;
   let el = target
   // console.log('togglenext', e)
   if (targetId) {
      el = document.getElementById(targetId)
   }
   hideclass.forEach((classname => el.nextElementSibling.classList.remove(classname)))
   if (hideclass.some(classname => el.nextElementSibling.classList.contains(classname))) {
      classes(target)
      classes(el.nextElementSibling)
   } else {
      classes(target, true)
   }
   classes(el.nextElementSibling, true)
}

/**
 * @deprecated because it uses id instead of selector
 */
export function showprev(e, targetId) {
   pd(e);
   const target = e.parenttarget || e.target;
   let el = target
   // console.log('togglenext', e)
   if (targetId) {
      el = document.getElementById(targetId)
   }
   hideclass.forEach((classname => el.previousElementSibling.classList.remove(classname)))
   if (hideclass.some(classname => el.previousElementSibling.classList.contains(classname))) {
      classes(target)
      classes(el.previousElementSibling)
   } else {
      classes(target, true)
   }
   classes(el.previousElementSibling, true)
}

export function addquery(e, name, value, valuetype) {
   pd(e);
   const query = getQueryParam(name)
   if (valuetype) {
      //TODO get value from selector
      const values = extractData(e, value, valuetype)
      value = values[valuetype].pop()[valuetype]
      // console.log('addquery',value)
   }
   if (query) {
      const data = query.split('|')
      if (data.includes(value)) {
         const index = data.indexOf(value)
         if (index !== -1) {
            data.splice(index, 1)
         }
      }
      if (value) {
         data.push(value)
      }
      if (data.length) {
         const newval = data.join('|')
         // console.log('eventhandler', name, value, {'query': query, newdata: newdata, newval: newval})
         setQueryParam(name, newval)
      } else {
         setQueryParam(name)
      }
   } else {
      setQueryParam(name, value)
   }
   setParamOnUrls(name, getQueryParam(name))
}

export function remquery(e, name, value, valueisselector) {
   pd(e);
   const query = getQueryParam(name)
   if (query) {
      if (value) {
         const data = query.split('|')
         if (data.includes(value)) {
            const index = data.indexOf(value)
            if (index !== -1) {
               data.splice(index, 1)
            }
         }
         if (data.length) {
            const newval = data.join('|')
            // console.log('eventhandler', name, value, {'query': query, newdata: newdata, newval: newval})
            setQueryParam(name, newval)
         } else {
            setQueryParam(name)
         }
         setParamOnUrls(name, getQueryParam(name))
      } else {
         setQueryParam(name)
      }

   }
}

export function togglequery(e, name, value, isselector) {
   pd(e);
   if (isselector) {
      value = getSingleValue(e, value)
   }
   const query = getQueryParam(name)
   if (query) {
      const data = query.split('|')
      if (data.includes(value)) {
         const index = data.indexOf(value)
         if (index !== -1) {
            data.splice(index, 1)
         }
      } else {
         data.push(value)
      }
      if (data.length) {
         const newval = data.join('|')
         // console.log('eventhandler', name, value, {'query': query, newdata: newdata, newval: newval})
         setQueryParam(name, newval)
      } else {
         setQueryParam(name)
      }
   } else {
      setQueryParam(name, value)
   }
   setParamOnUrls(name, getQueryParam(name))

}

export function replacequery(e, name, value, isselector) {
   pd(e);
   if (isselector) {
      value = getSingleValue(e, value)
   }
   setQueryParam(name, value)
   setParamOnUrls(name, getQueryParam(name))
}

export function getSingleValue(e, selector) {
   const coll = getElements(e, selector)
   const el = coll.pop()[0]
   let value = selector
   switch (el.tagName.toLowerCase()) {
      case'select':
         const values = Array.from(el.selectedOptions).map(option => option.value);
         if (values.length) {
            if (values.length === 1) {
               value = values.pop()
            } else {
               console.log('replacequery', name, value, isselector, 'multiselect not implemented')
               value = ''
            }
         } else {
            value = ''
         }
         break;
      case'input':
         value = el.value
         break;
      default:
         value = el.innerHTML
   }
   // console.log('getSingleValue', selector, value)
   return value
}

export function toggleclass(e, selector, ...classnames) {
   pd(e);
   const coll = getElements(e, selector)
   // console.log(elems)
   coll.forEach(elems => {
      elems.forEach(el => {
         classnames.forEach(c => {
            el.classList.toggle(c)
         })
      })
   })
}

export function addclass(e, selector, ...classnames) {
   pd(e);
   const coll = getElements(e, selector)
   coll.forEach(elems => {
      elems.forEach(el => {
         classnames.forEach(c => {
            el.classList.add(c)
         })
      })
   })
}

export function remclass(e, selector, ...classnames) {
   pd(e);
   const coll = getElements(e, selector)
   coll.forEach(elems => {
      elems.forEach(el => {
         classnames.forEach(c => {
            el.classList.remove(c)
         })
      })
   })

}

export async function submit(e, destination) {
   pd(e);
   const target = e.parenttarget || e.target;
   let el;
   if (target.slotElement) {
      el = target.shadowRoot.activeElement
   } else {
      el = target
   }
   const form = el.closest('form');
   // console.log('submit', el, form)
   if (form) {
      const submitEvent = new Event('submit', {
         bubbles: true,
         cancelable: true
      })
      const formData = new FormData(form)
      formData.append(el.name, el.value)

      const tempInput = document.createElement('input')
      tempInput.type = 'hidden'
      tempInput.name = el.name
      tempInput.value = el.value
      form.appendChild(tempInput)

      const referer = document.createElement('input')
      referer.type = 'hidden'
      referer.name = '_referer'
      referer.value = window.location.toString()
      form.appendChild(referer)

      form.querySelectorAll('input').forEach(el => {
         if (el.checkValidity()) {
            el.classList.remove('g-inv');
         } else {
            el.classList.add('g-inv');
         }
      });
      if (form.checkValidity() && form.dispatchEvent(submitEvent)) {
         await form.submit();
      }

      // Remove the temporary input
      form.removeChild(tempInput);
      form.removeChild(referer);
   }

}

/**
 * add attribute to element
 */
export function addattr(e, selector, ...attributes) {
   pd(e);
   const coll = getElements(e, selector)
   attributes.forEach(attrib => {
      const [name, value] = attrib.split('=')
      coll.forEach(elems => {
         elems.forEach(el => {
            el.setAttribute(name, value.replace(/\\\\/g, ' '))
         })
      })
   })
}

/**
 * remove attribute from element
 */
export function remattr(e, selector, ...attributes) {
   pd(e);
   const coll = getElements(e, selector)

   attributes.forEach(attrib => {
      const [name, value] = attrib.split('=')
      coll.forEach(elems => {
         elems.forEach(el => {
            el.removeAttribute(name)
         })
      })
   })
}

/**
 * toggle attributes of elements
 */
export function toggleattr(e, selector, ...attributes) {
   pd(e);
   const coll = getElements(e, selector)
   attributes.forEach(attribute => {
      const [name, value] = attribute.split('=')
      coll.forEach(elems => {
         elems.forEach(el => {
            if (el.hasAttribute(name) && el.getAttribute(name) === value.replace(/\\\\/g, ' ')) {
               remattr(e, selector, attribute)
            } else {
               addattr(e, selector, attribute)
            }
         })
      })
   })

}


/**
 * sends filtered element attributes
 * puts all received into e.collection
 * @example send|collection|value|/path/to/handler
 * @example send|self|name|/path/to/handler
 * @param e
 * @param selector
 * @param type (html,value,attribute name)
 * @param endpoint
 */
export async function send(e, selector, type, endpoint) {
   const data = extractData(e, selector, type)
   data['referer'] = window.location.toString()
   const q = getQuery()
   const destinationurl = q ? endpoint + (endpoint.includes('?') ? '&' : '?') + q : endpoint;
   if (Object.keys(data).length) {
      const formData = new FormData()
      console.log('send data', data)
      Object.keys(data).forEach(k => {
         if (k === 'form') {
            console.log('send data[' + k, data[k])
            data[k].forEach((form) => {
               Object.keys(form['form']).forEach((val) => {
                  console.log('send ', val, form['form'][val])
                  const formentry = form['form'][val]
                  if (formentry?.name) {
                     console.log('todo send File')
                     // upload file
                     formData.append(val, formentry);
                     // save filename just for fun
                     form['form'][val] = formentry.name;
                  }
               });
            });
         }
         formData.append(k, JSON.stringify(data[k]))

      })

      try {
         console.log('send url', destinationurl)
         const token = getCookie('csrftoken')
         const headers = {}
         if (token) {
            headers["X-CSRF-Token"] = token
         }
         const response = await fetch(destinationurl, {
            method: 'POST',
            headers,
            body: formData
         })
         if (response.ok) {
            const responsecopy = response.clone();
            try {
               const resdata = await response.json()
               e.collection = [resdata]
            } catch (error) {
               e.collection = [[{'html': await responsecopy.text()}]]
            }
            console.log('send', 'collection', e.collection)
         } else {
            console.log('send', 'Response not ok', response);
         }
      } catch (e) {
         console.error('send', 'fetch failed', e);
      }
   }
}

/**
 * extract data from Elements
 * @param e (event)
 * @param selector (single queryselector)
 * @param type comma separated list (html,value,attribute name,form)
 * @returns {{}}
 */
function extractData(e, selector, type) {
   const coll = getElements(e, selector)
   const types = type.split(',')
   const data = {}
   // console.log('send', coll, selector, types)
   if (coll) {
      coll.forEach(list => {
         list.forEach(el => {
            // console.log('send', el)
            types.forEach(typ => {
               if (!data[typ]) {
                  data[typ] = []
               }
               let v = undefined
               switch (typ) {
                  case 'form':
                     v = {}
                     let out = {};
                     new FormData(el).forEach((v, k) => {
                        // console.log('form', k, v,typeof v)
                        out[k] = v;
                     });
                     v[typ] = out;
                     break;
                  case 'html':
                     v = {}
                     v[typ] = el.innerHTML;
                     break;
                  case 'value':
                     const name = el.getAttribute('name') || el.getAttribute('id')
                     switch (el.nodeName.toLowerCase()) {
                        case'select':
                           const values = Array.from(el.selectedOptions).map(option => option.value);
                           if (values.length) {
                              v = {}
                              if (values.length === 1) {
                                 v[name] = values.pop()
                              } else {
                                 v[name] = values
                              }
                           }
                           break;
                        default:
                           if (el.value) {
                              v = {}
                              v[name] = el.value
                           }
                     }
                     break;
                  default:
                     if (el.getAttribute && el.getAttribute(typ)) {
                        v = {}
                        v[typ] = el.getAttribute(typ)
                     } else {
                        console.log('extractData', el, typ)
                     }
               }
               if (v) {
                  data[typ].push(v)
               }
            })
         })
      })
   }
   return data;
}

/**
 * @lol very simple tabs switcher
 */
export function tabswitch(e, hide_class) {
   pd(e);
   const hideclasses = hide_class ? [hide_class] : hideclass
   const target = e.parenttarget || e.target;
   const parent = target.parentElement;
   const tabname = target.getAttribute('name')
   const tabcontent = document.getElementById(target.getAttribute('name'))
   const queryparam = parent.getAttribute('data-tabswitch-queryparam')
   if (tabcontent) {
      hideclasses.forEach(hc => tabcontent.classList.remove(hc))
   }
   addquery(e, queryparam, tabname)
   const activebtnstyle = parent.getAttribute('data-tabswitch-classes').split(' ')

   activebtnstyle.forEach(c => {
      target.classList.add(c)
   })

   const siblings = Array.from(target.parentNode.children).filter(child => child !== target)
   siblings.forEach(el => {
      const name = el.getAttribute('name')
      if (name) {
         activebtnstyle.forEach(c => {
            el.classList.remove(c)
         })
         remquery(e, queryparam, name)
         const elem = document.getElementById(name)
         if (elem) {
            hideclasses.forEach(hc => elem.classList.add(hc))
         }
      }
   })
}

/**
 * @lol click other elements by selector
 *
 */
export function click(e, selector) {
   pd(e);
   const coll = getElements(e, selector)
   coll.forEach(list => {
      list.forEach(el => {
         el.click()
      })
   })
}

/**
 * param: (id prefix)
 */
export function remelems(e, selector, type, prefix) {
   pd(e);
   const coll = getElements(e, selector)
   console.log('remelems', type, prefix, coll)
   switch (type) {
      case 'el':
         coll.forEach(list => {
            list.forEach(el => {
               el.remove()
            })
         })
         break;
      case 'parent':
         coll.forEach(list => {
            list.forEach(el => {
               el.parentElement.remove()
            })
         })
         break;
      case 'id':
         prefix = prefix || ''
         coll.forEach(list => {
            list.forEach(item => {
               const id = item['id']
               document.getElementById(prefix + id)?.remove()
            })
         })
         break;
   }

}


/**
 * @lol collect elements by selector and put em into a collection inside the current event
 * get the list in every eventhandler for further usage
 * @example collect|select[name=somevalue]|#someid
 * use \\ for space character
 */
export function collect(e, ...selectors) {
   e.collection = []
   const coll = e.collection
   selectors.forEach(selector => {
      const found = getElements(e, selector)?.pop()
      // console.log('collect', selector, found)
      if (found) {
         coll.push(found)
      }
   })
   // console.log('collect collection', e.collection)
}

/**
 * @lol write content to destination
 * @example write|collection|html,#id,inner
 * @example write|self|name,#id,inner
 * @example write|self|value,#id,inner
 * @param e Eventhandler
 * @param selector
 * @param targets <['key,targetId,mode'] >
 */
export function write(e, selector, ...targets) {
   const coll = getElements(e, selector)
   targets.forEach(dest => {
      const [name, targetselector, mode] = dest.split(',')
      const targets = getElements(e, targetselector)
      // console.log('write', name, targetselector, mode, coll, targets)
      if (targets[0] && targets[0][0]) {
         const target = targets[0][0]

         runCollection(coll, (item) => {
            if (item[name]) {
               let elem = dom(item[name])
               switch (mode) {
                  case'outer':
                     target.replaceWith(elem)
                     break;
                  case'inner':
                     elem = target
                     target.innerHTML = item[name]
                     break;
                  case'append':
                     target.append(elem)
                     break;
                  default:
                     console.log('eventhandler.write: missing mode param after ', name, item[name])
               }
               dataListener(elem)
            }
         })
      } else {
         console.log('write no targets found with selector ', targetselector, targets)
      }

      // console.log('write', name, targetid, coll)
   })
}

/**
 * refresh content from server
 * @example refresh|/endpoint/path|see write() for examples|...
 */
export async function refresh(e, endpoint, ...targets) {
   pd(e);
   const coll = {collection: [[]]};
   await send(coll, 'collection', 'none', endpoint)
   write(coll, 'collection', ...targets)
}

/**
 * collect some names for use in refreshcomp
 * @param e
 * @param items
 */
export function tocollection(e, ...items) {
   e.collection = [{'refresh': items}]
}

/**
 * refresh components
 */
export async function refreshcomp(e, endpoint) {
   pd(e);
   const coll = e.collection;
   // console.log('refreshcomp', endpoint, coll)
   coll.forEach(list => {
      list.refresh?.forEach(item => {
         refresh({}, endpoint + item, 'html,#comp_' + item + ',inner')
      })
   })
}

/**
 * show errors from response
 */
export async function showerrors(e) {
   const coll = e.collection;
   coll.forEach(list => {
      const data = list.errors;
      if (data) {
         Object.keys(data).forEach(id => {
            const elem = document.getElementById(id)
            const text = data[id]
            elem.innerHTML = text;
            if (text) {
               elem.classList.remove(hideclass)
            } else {
               elem.classList.add(hideclass)
            }
            console.log('errorcheck', id, data[id])
         })
      }
   })
}

export async function checkrequired(e, selector) {
   const coll = getElements(e, selector)
   // console.log('template', e, selector, coll)
   coll.forEach(list => {
      list.forEach(item => {
         console.log('checkrequired', item.nodeName)
         if (item.nodeName.toLowerCase() === 'form') {
            item.querySelectorAll('input').forEach(el => {
               if (el.checkValidity()) {
                  el.classList.remove('g-inv');
               } else {
                  el.classList.add('g-inv');
                  e.notconfirmed = true
                  pd(e);
               }
            });
         } else {
            if (item.checkValidity()) {
               item.classList.remove('g-inv');
            } else {
               item.classList.add('g-inv');
               e.notconfirmed = true
               pd(e);
            }
         }

      })
   })
}

/**
 * activate a template
 * <template><div>show me</div></template>
 *
 * @param e
 * @param selector
 */
export function template(e, selector) {
   pd(e);
   const coll = getElements(e, selector)
   // console.log('template', e, selector, coll)
   coll.forEach(list => {
      list.forEach(item => {
         // console.log('template',item)
         if (item?.tagName?.toLowerCase() === 'template') {
            let content = item.content.cloneNode(true)
            item.replaceWith(content)
         }
      })
   })
}

/**
 * show confirm dialog
 * @param e
 * @param question
 * @returns {Promise<void>}
 */
export async function ask(e, ...question) {
   const answer = confirm(question.join(' '))
   if (answer === false) {
      e.notconfirmed = true
      pd(e);
   }
}


/**
 * run next handler if key matches
 * TODO fix virtual keyboard
 * @param e
 * @param keys
 */
export function key(e, ...keys) {
   let haskey = false
   keys.forEach(key => {
      if (e.code.toLowerCase() === key.toLowerCase()) {
         haskey = true;
      }
   })
   if (haskey === false) {
      e.notconfirmed = true
      pd(e);
   }
}

/**
 *  press keys if key
 */
export function press(e, eventkey, ...keys) {
   if (eventkey !== e.code) {
      return
   }
   keys.forEach(key => {
      const keyorig = key;
      key = key.toLowerCase();


      switch (key) {
         case 'tab': {
            if (e.code.toLowerCase() === key) {
               return;
            }
            pd(e);
            const f = e.target.form;
            if (!f) {
               break;
            }

            const els = [...f.elements].filter(el => !el.disabled && el.tabIndex !== -1);
            els[els.indexOf(e.target) + (e.shiftKey ? -1 : 1)]?.focus();
            break;
         }
         case 'enter':
         case 'escape':
         case 'backspace':
         case 'delete':
         case 'home':
         case 'end':
         case 'pageup':
         case 'pagedown':
         case 'arrowup':
         case 'arrowdown':
         case 'arrowleft':
         case 'arrowright':
         case ' ':
         case 'space':
            if (e.code.toLowerCase() === key) {
               return;
            }
            document.dispatchEvent(
               new KeyboardEvent('keydown', {
                  key: key === 'space' ? ' ' : key,
                  bubbles: true,
                  cancelable: true
               })
            );
            break;

         default:
            if (e.code === keyorig) {
               return;
            }
            document.dispatchEvent(
               new KeyboardEvent('keydown', {
                  key: keyorig,
                  bubbles: true,
                  cancelable: true
               })
            );
      }
   });
}


/**
 *  create collection if key matches
 *
 */
export function collectonkey(e, ...keys) {
   e.collection = []
   const coll = e.collection
   keys.forEach(key => {
      if (e.code.toLowerCase() === key.toLowerCase()) {
         coll.push(e.target)
      }
   })
}

export function back(e) {
   if (e.ctrlKey) {
      return;
   }
   pd(e);
   e.notconfirmed = true
   history.back()
}

export function debug(e) {
   console.log('debug', 'collection', e.collection, 'e.target', e.target, 'e.parenttarget:', e.parenttarget, 'e', e,)
}


export function nottarget(e, selector) {
   const coll = getElements(e, selector)
   if (hasNodes(coll)) {
      runCollection(coll, item => {
         // console.log('not target',e.target,item)
         if (e.target === item) {
            e.notconfirmed = true;
         }
      })
   } else {
      e.notconfirmed = true
   }
}

export function stopif(e, selector) {
   const coll = getElements(e, selector)
   e.notconfirmed = !!hasNodes(coll);
}

/**
 *  run next handler if selectors have changed
 *  data-input="addclass|button[name=save]|g-btn-secondary ifchange|self\\input|self\\textarea|self\\select|self\\checkbox remclass|button[name=save]|g-btn-secondary"
 */
export function ifchange(e, ...selectors) {
   let changed = false;

   selectors.forEach((selector) => {
      const coll = getElements(e, selector);
      runCollection(coll, (el) => {
         // <input type="file"> → nur prüfen ob etwas ausgewählt ist
         if (el instanceof HTMLInputElement && el.type === 'file') {
            if (el.files && el.files.length > 0) {
               changed = true;
            }
            return;
         }

         // <select>
         if (el instanceof HTMLSelectElement) {
            if ([...el.options].some(o => o.selected !== o.defaultSelected)) {
               changed = true;
            }
            return;
         }

         // checkbox / radio
         if (el instanceof HTMLInputElement && (el.type === 'checkbox' || el.type === 'radio')) {
            if (el.checked !== el.defaultChecked) {
               changed = true;
            }
            return;
         }

         // contenteditable
         if (el instanceof HTMLElement && el.isContentEditable) {
            const k = 'origHtml';
            if (el.dataset[k] === undefined) {
               el.dataset[k] = el.innerHTML;
            }
            if (el.innerHTML !== el.dataset[k]) {
               changed = true;
            }
            return;
         }

         // input / textarea
         if ('value' in el && 'defaultValue' in el) {
            if (el.value !== el.defaultValue) {
               changed = true;
            }
         }
      });
   });

   if (changed === false) {
      stop(e);
   }
}


function classes(el, on) {
   const classes = el.getAttribute('data-active-classes')
   if (classes) {
      classes.split(' ').forEach(c => {
         if (on) {
            el.classList.add(c)
         } else {
            el.classList.remove(c)
         }
      })
   }
}

export function runCollection(coll, callback) {
   // console.log('template', e, selector, coll)
   iterateGetElements(coll, callback)
}

export function pd(e) {
   if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
   }
   // e.notconfirmed = true;
}

export function stop(e) {
   e.notconfirmed = true;
}

function hasNodes(coll) {
   let out = false;
   runCollection(coll, el => out = true)
   return out;
}


