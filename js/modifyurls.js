export function init(apiName, targetName, paramName) {
   return e => {
      // console.log('modifyurls',e.detail,'apiName',apiName,'targetName',targetName)
      if (e.detail.apiName === apiName && e.detail.targetName === targetName) {
         e.preventDefault()
         // console.log(apiName, e.detail)
         const url = new URL(e.detail.url, window.location.origin)
         const query = new URLSearchParams(url.search)
         const paramval = query.get(paramName)
         setParamOnUrls(paramName, paramval, e.detail.apiElement)
      }
   }
}

export function setParamOnUrls(paramName, paramval, parent) {
   setParamOnHref(paramName, paramval, parent)
   setParamOnAction(paramName, paramval, parent)
}

export function getQuery() {
   const url = new URL(window.location.toString(), window.location.origin)
   // console.log('getQuery', url)
   const q = new URLSearchParams(url.search)
   const qs = (q && typeof q !== 'string') ? q.toString() : ((q || '').toString())
   return qs.replace(/^\?/, '')
}

function setParamOnHref(paramName, paramval, parent) {
   const elems = document.getElementsByTagName('A')
   for (let a = 0, b = elems.length; a < b; a++) {
      let el = elems.item(a)
      if (!hasParent(el, parent)) {
         changeParamOnElement(el, 'href', paramName, paramval)
      }
   }
}

function setParamOnAction(paramName, paramval, parent) {
   const elems = document.getElementsByTagName('FORM')
   for (let a = 0, b = elems.length; a < b; a++) {
      let el = elems.item(a)
      if (!hasParent(el, parent)) {
         changeParamOnElement(el, 'action', paramName, paramval)
      }
   }
}

function changeParamOnElement(el, attr, name, value) {
   let href = new URL(el.getAttribute(attr), window.location.origin)
   let query = href.searchParams
   if (value) query.set(name, value)
   else query.delete(name)
   el.setAttribute(attr, href.toString())

}

function hasParent(child, parent) {
   let currentElement = child;
   while (currentElement) {
      if (currentElement === parent) {
         return true;
      }
      currentElement = currentElement.parentElement;
   }
   return false;
}


