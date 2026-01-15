import {setQueryParam, getQueryParam, hasQueryParam} from '/system.js';
// pagination.js

import {attachListener, customEvent} from '/dataloader.js'


let scrollcount = [];

function getMaxScrolled(name) {
   let max = 0
   for (let i = 0; i < scrollcount.length; i++) {
      if (scrollcount[i].name === name && scrollcount[i].valid) {
         max = Math.max(scrollcount[i].num, max)
      }
   }
   return max
}

function getMinScrolled(name) {
   let min = scrollcount.length
   for (let i = 0; i < scrollcount.length; i++) {
      if (scrollcount[i].name === name && scrollcount[i].valid) {
         min = Math.min(scrollcount[i].num, min)
      }
   }
   return min
}


function allowedScroll(name, num) {
   for (let i = 0; i < scrollcount.length; i++) {
      if (scrollcount[i].name === name && scrollcount[i].num === num) {
         return scrollcount[i].valid;
      }
   }
   return true;
}

function addScrolled(name, num, valid) {
   if (!hasScrolled(name, num))
      scrollcount.push({name: name, num: num, valid: valid})
}

export function remScrolled(name) {
   // console.log(name, scrollcount)
   scrollcount = scrollcount.filter(item => item.name !== name)
   generateScrollcount(name)
}

function hasScrolled(name, num) {
   for (let i = 0; i < scrollcount.length; i++) {
      if (scrollcount[i].name === name && scrollcount[i].num === num) {
         return true;
      }
   }
   return false;
}

function generateScrollcount(name) {
   const el = document.getElementById(name)
   if (el) {
      const items = el.querySelectorAll('a.separator')
      items.forEach(el => addScrolled(name, parseInt(el.getAttribute('data-scrollable-page')), true))
   }
   // console.log(scrollcount)
}

function handleScroll(event, listName) {
   const element = event.target;
   const scrollTop = parseInt(element.scrollTop);
   const scrollHeight = parseInt(element.scrollHeight);
   const clientHeight = parseInt(element.clientHeight);
   const currentPage = parseInt(getQueryParam(listName + '_page')) || 0;
   const downscroll = scrollTop + clientHeight >= scrollHeight - 2
   if (allowedScroll(listName, currentPage)) {
      if (scrollTop === 0) {
         // Scroll up
         if (currentPage > 0) {
            const nextpage = Math.max(getMinScrolled(listName) - 1, 0, currentPage - 1)
            if (!hasScrolled(listName, nextpage)) send(listName, 'up', nextpage, element);
         }
      } else if (downscroll) {
         // Scroll down
         const nextpage = Math.max(getMaxScrolled(listName), currentPage) + 1
         if (!hasScrolled(listName, nextpage)) send(listName, 'down', nextpage, element);
      }
   }
}

function send(id, direction, nextpage, element) {
   const el = document.getElementById(id)
   if (!customEvent('scrollable.js', id, el, element, direction, nextpage)) {
      addScrolled(id, nextpage, false)
   }
}

export function init() {

   const lists = document.querySelectorAll('[data-scrollable]');
   lists.forEach(list => {
      const listName = list.getAttribute('data-scrollable');
      if (!hasScrollbar(list)) {
         // console.log(list)
         forceScrollbar(list)
      }
      const currentPage = parseInt(getQueryParam(listName + '_page')) || 0;
      if (currentPage > 0) list.scrollTop = list.clientHeight / 3.5
      list.addEventListener('scroll', (event) => handleScroll(event, listName));
   });
}

async function forceScrollbar(list) {
   const el = document.createElement('div');
   list.appendChild(el)
   function animate() {
      if (!hasScrollbar(list)) {
         const h = parseInt(el.style.height || 0);
         el.style.height = (20 + h) + 'px';
         requestAnimationFrame(animate);
      }
   }
   requestAnimationFrame(animate);
}

function hasScrollbar(element) {
   return element.scrollHeight > element.clientHeight || element.scrollWidth > element.clientWidth;
}

export function eventScrolled(callback) {
   window.addEventListener('scrollable.js', callback)
}
