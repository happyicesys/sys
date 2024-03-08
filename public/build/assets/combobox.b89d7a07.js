import{j as S,u as Y,A as Se,z as N,aa as ae,a3 as ye,g as D,q as W,s as L,B as de,y as Z,H as Oe,D as Ie,ab as k,h as le,F as Re,C as ve,x as Ce,G as B}from"./app.c4e47028.js";import{o as O,u as U,b as Ee,i as G,E as Te,A as q,T as oe,t as J,N as X,l as we,a as $}from"./keyboard.58689cfa.js";import{i as ce,d as Me,f as Pe,s as ze,O as De,o as re,a as ie,n as Fe}from"./disposables.be045d92.js";import{s as $e}from"./use-resolve-button-type.ef81a21b.js";/**
 * vue-virtual
 *
 * Copyright (c) TanStack
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE.md file in the root directory of this source tree.
 *
 * @license MIT
 */function Q(){return Q=Object.assign?Object.assign.bind():function(o){for(var a=1;a<arguments.length;a++){var e=arguments[a];for(var l in e)Object.prototype.hasOwnProperty.call(e,l)&&(o[l]=e[l])}return o},Q.apply(this,arguments)}/**
 * virtual-core
 *
 * Copyright (c) TanStack
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE.md file in the root directory of this source tree.
 *
 * @license MIT
 */function ee(){return ee=Object.assign?Object.assign.bind():function(o){for(var a=1;a<arguments.length;a++){var e=arguments[a];for(var l in e)Object.prototype.hasOwnProperty.call(e,l)&&(o[l]=e[l])}return o},ee.apply(this,arguments)}/**
 * virtual-core
 *
 * Copyright (c) TanStack
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE.md file in the root directory of this source tree.
 *
 * @license MIT
 */function K(o,a,e){var l,n=(l=e.initialDeps)!=null?l:[],t;return function(){var i;e.key&&e.debug!=null&&e.debug()&&(i=Date.now());var r=o(),u=r.length!==n.length||r.some(function(P,w){return n[w]!==P});if(!u)return t;n=r;var c;if(e.key&&e.debug!=null&&e.debug()&&(c=Date.now()),t=a.apply(void 0,r),e.key&&e.debug!=null&&e.debug()){var m=Math.round((Date.now()-i)*100)/100,p=Math.round((Date.now()-c)*100)/100,T=p/16,I=function(w,A){for(w=String(w);w.length<A;)w=" "+w;return w};console.info("%c\u23F1 "+I(p,5)+" /"+I(m,5)+" ms",`
            font-size: .6rem;
            font-weight: bold;
            color: hsl(`+Math.max(0,Math.min(120-120*T,120))+"deg 100% 31%);",e==null?void 0:e.key)}return e==null||e.onChange==null||e.onChange(t),t}}function ne(o,a){if(o===void 0)throw new Error("Unexpected undefined"+(a?": "+a:""));return o}var Ae=function(a,e){return Math.abs(a-e)<1};/**
 * virtual-core
 *
 * Copyright (c) TanStack
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE.md file in the root directory of this source tree.
 *
 * @license MIT
 */var Ve=function(a){return a},je=function(a){for(var e=Math.max(a.startIndex-a.overscan,0),l=Math.min(a.endIndex+a.overscan,a.count-1),n=[],t=e;t<=l;t++)n.push(t);return n},ke=function(a,e){var l=a.scrollElement;if(!!l){var n=function(r){var u=r.width,c=r.height;e({width:Math.round(u),height:Math.round(c)})};n(l.getBoundingClientRect());var t=new ResizeObserver(function(i){var r=i[0];if(r!=null&&r.borderBoxSize){var u=r.borderBoxSize[0];if(u){n({width:u.inlineSize,height:u.blockSize});return}}n(l.getBoundingClientRect())});return t.observe(l,{box:"border-box"}),function(){t.unobserve(l)}}},Be=function(a,e){var l=a.scrollElement;if(!!l){var n=function(){e(l[a.options.horizontal?"scrollLeft":"scrollTop"])};return n(),l.addEventListener("scroll",n,{passive:!0}),function(){l.removeEventListener("scroll",n)}}},Ne=function(a,e,l){if(e!=null&&e.borderBoxSize){var n=e.borderBoxSize[0];if(n){var t=Math.round(n[l.options.horizontal?"inlineSize":"blockSize"]);return t}}return Math.round(a.getBoundingClientRect()[l.options.horizontal?"width":"height"])},Le=function(a,e,l){var n,t,i=e.adjustments,r=i===void 0?0:i,u=e.behavior,c=a+r;(n=l.scrollElement)==null||n.scrollTo==null||n.scrollTo((t={},t[l.options.horizontal?"left":"top"]=c,t.behavior=u,t))},_e=function(a){var e=this;this.unsubs=[],this.scrollElement=null,this.isScrolling=!1,this.isScrollingTimeoutId=null,this.scrollToIndexTimeoutId=null,this.measurementsCache=[],this.itemSizeCache=new Map,this.pendingMeasuredCacheIndexes=[],this.scrollDirection=null,this.scrollAdjustments=0,this.measureElementCache=new Map,this.observer=function(){var l=null,n=function(){return l||(typeof ResizeObserver<"u"?l=new ResizeObserver(function(i){i.forEach(function(r){e._measureElement(r.target,r)})}):null)};return{disconnect:function(){var i;return(i=n())==null?void 0:i.disconnect()},observe:function(i){var r;return(r=n())==null?void 0:r.observe(i,{box:"border-box"})},unobserve:function(i){var r;return(r=n())==null?void 0:r.unobserve(i)}}}(),this.range=null,this.setOptions=function(l){Object.entries(l).forEach(function(n){var t=n[0],i=n[1];typeof i>"u"&&delete l[t]}),e.options=ee({debug:!1,initialOffset:0,overscan:1,paddingStart:0,paddingEnd:0,scrollPaddingStart:0,scrollPaddingEnd:0,horizontal:!1,getItemKey:Ve,rangeExtractor:je,onChange:function(){},measureElement:Ne,initialRect:{width:0,height:0},scrollMargin:0,scrollingDelay:150,indexAttribute:"data-index",initialMeasurementsCache:[],lanes:1},l)},this.notify=function(l){e.options.onChange==null||e.options.onChange(e,l)},this.maybeNotify=K(function(){return e.calculateRange(),[e.isScrolling,e.range?e.range.startIndex:null,e.range?e.range.endIndex:null]},function(l){e.notify(l)},{key:!1,debug:function(){return e.options.debug},initialDeps:[this.isScrolling,this.range?this.range.startIndex:null,this.range?this.range.endIndex:null]}),this.cleanup=function(){e.unsubs.filter(Boolean).forEach(function(l){return l()}),e.unsubs=[],e.scrollElement=null},this._didMount=function(){return e.measureElementCache.forEach(e.observer.observe),function(){e.observer.disconnect(),e.cleanup()}},this._willUpdate=function(){var l=e.options.getScrollElement();e.scrollElement!==l&&(e.cleanup(),e.scrollElement=l,e._scrollToOffset(e.scrollOffset,{adjustments:void 0,behavior:void 0}),e.unsubs.push(e.options.observeElementRect(e,function(n){e.scrollRect=n,e.maybeNotify()})),e.unsubs.push(e.options.observeElementOffset(e,function(n){e.scrollAdjustments=0,e.scrollOffset!==n&&(e.isScrollingTimeoutId!==null&&(clearTimeout(e.isScrollingTimeoutId),e.isScrollingTimeoutId=null),e.isScrolling=!0,e.scrollDirection=e.scrollOffset<n?"forward":"backward",e.scrollOffset=n,e.maybeNotify(),e.isScrollingTimeoutId=setTimeout(function(){e.isScrollingTimeoutId=null,e.isScrolling=!1,e.scrollDirection=null,e.maybeNotify()},e.options.scrollingDelay))})))},this.getSize=function(){return e.scrollRect[e.options.horizontal?"width":"height"]},this.memoOptions=K(function(){return[e.options.count,e.options.paddingStart,e.options.scrollMargin,e.options.getItemKey]},function(l,n,t,i){return e.pendingMeasuredCacheIndexes=[],{count:l,paddingStart:n,scrollMargin:t,getItemKey:i}},{key:!1}),this.getFurthestMeasurement=function(l,n){for(var t=new Map,i=new Map,r=n-1;r>=0;r--){var u=l[r];if(!t.has(u.lane)){var c=i.get(u.lane);if(c==null||u.end>c.end?i.set(u.lane,u):u.end<c.end&&t.set(u.lane,!0),t.size===e.options.lanes)break}}return i.size===e.options.lanes?Array.from(i.values()).sort(function(m,p){return m.end-p.end})[0]:void 0},this.getMeasurements=K(function(){return[e.memoOptions(),e.itemSizeCache]},function(l,n){var t=l.count,i=l.paddingStart,r=l.scrollMargin,u=l.getItemKey,c=e.pendingMeasuredCacheIndexes.length>0?Math.min.apply(Math,e.pendingMeasuredCacheIndexes):0;e.pendingMeasuredCacheIndexes=[];for(var m=e.measurementsCache.slice(0,c),p=c;p<t;p++){var T=u(p),I=e.options.lanes===1?m[p-1]:e.getFurthestMeasurement(m,p),P=I?I.end:i+r,w=n.get(T),A=typeof w=="number"?w:e.options.estimateSize(p),x=P+A,s=I?I.lane:p%e.options.lanes;m[p]={index:p,start:P,size:A,end:x,key:T,lane:s}}return e.measurementsCache=m,m},{key:!1,debug:function(){return e.options.debug}}),this.calculateRange=K(function(){return[e.getMeasurements(),e.getSize(),e.scrollOffset]},function(l,n,t){return e.range=l.length>0&&n>0?Ke({measurements:l,outerSize:n,scrollOffset:t}):null},{key:!1,debug:function(){return e.options.debug}}),this.getIndexes=K(function(){return[e.options.rangeExtractor,e.calculateRange(),e.options.overscan,e.options.count]},function(l,n,t,i){return n===null?[]:l(ee({},n,{overscan:t,count:i}))},{key:!1,debug:function(){return e.options.debug}}),this.indexFromElement=function(l){var n=e.options.indexAttribute,t=l.getAttribute(n);return t?parseInt(t,10):(console.warn("Missing attribute name '"+n+"={index}' on measured element."),-1)},this._measureElement=function(l,n){var t=e.measurementsCache[e.indexFromElement(l)];if(!t||!l.isConnected){e.measureElementCache.forEach(function(u,c){u===l&&(e.observer.unobserve(l),e.measureElementCache.delete(c))});return}var i=e.measureElementCache.get(t.key);i!==l&&(i&&e.observer.unobserve(i),e.observer.observe(l),e.measureElementCache.set(t.key,l));var r=e.options.measureElement(l,n,e);e.resizeItem(t,r)},this.resizeItem=function(l,n){var t,i=(t=e.itemSizeCache.get(l.key))!=null?t:l.size,r=n-i;r!==0&&(l.start<e.scrollOffset&&e._scrollToOffset(e.scrollOffset,{adjustments:e.scrollAdjustments+=r,behavior:void 0}),e.pendingMeasuredCacheIndexes.push(l.index),e.itemSizeCache=new Map(e.itemSizeCache.set(l.key,n)),e.notify(!1))},this.measureElement=function(l){!l||e._measureElement(l,void 0)},this.getVirtualItems=K(function(){return[e.getIndexes(),e.getMeasurements()]},function(l,n){for(var t=[],i=0,r=l.length;i<r;i++){var u=l[i],c=n[u];t.push(c)}return t},{key:!1,debug:function(){return e.options.debug}}),this.getVirtualItemForOffset=function(l){var n=e.getMeasurements();return ne(n[fe(0,n.length-1,function(t){return ne(n[t]).start},l)])},this.getOffsetForAlignment=function(l,n){var t=e.getSize();n==="auto"&&(l<=e.scrollOffset?n="start":l>=e.scrollOffset+t?n="end":n="start"),n==="start"?l=l:n==="end"?l=l-t:n==="center"&&(l=l-t/2);var i=e.options.horizontal?"scrollWidth":"scrollHeight",r=e.scrollElement?"document"in e.scrollElement?e.scrollElement.document.documentElement[i]:e.scrollElement[i]:0,u=r-e.getSize();return Math.max(Math.min(u,l),0)},this.getOffsetForIndex=function(l,n){n===void 0&&(n="auto"),l=Math.max(0,Math.min(l,e.options.count-1));var t=ne(e.getMeasurements()[l]);if(n==="auto")if(t.end>=e.scrollOffset+e.getSize()-e.options.scrollPaddingEnd)n="end";else if(t.start<=e.scrollOffset+e.options.scrollPaddingStart)n="start";else return[e.scrollOffset,n];var i=n==="end"?t.end+e.options.scrollPaddingEnd:t.start-e.options.scrollPaddingStart;return[e.getOffsetForAlignment(i,n),n]},this.isDynamicMode=function(){return e.measureElementCache.size>0},this.cancelScrollToIndex=function(){e.scrollToIndexTimeoutId!==null&&(clearTimeout(e.scrollToIndexTimeoutId),e.scrollToIndexTimeoutId=null)},this.scrollToOffset=function(l,n){var t=n===void 0?{}:n,i=t.align,r=i===void 0?"start":i,u=t.behavior;e.cancelScrollToIndex(),u==="smooth"&&e.isDynamicMode()&&console.warn("The `smooth` scroll behavior is not fully supported with dynamic size."),e._scrollToOffset(e.getOffsetForAlignment(l,r),{adjustments:void 0,behavior:u})},this.scrollToIndex=function(l,n){var t=n===void 0?{}:n,i=t.align,r=i===void 0?"auto":i,u=t.behavior;l=Math.max(0,Math.min(l,e.options.count-1)),e.cancelScrollToIndex(),u==="smooth"&&e.isDynamicMode()&&console.warn("The `smooth` scroll behavior is not fully supported with dynamic size.");var c=e.getOffsetForIndex(l,r),m=c[0],p=c[1];e._scrollToOffset(m,{adjustments:void 0,behavior:u}),u!=="smooth"&&e.isDynamicMode()&&(e.scrollToIndexTimeoutId=setTimeout(function(){e.scrollToIndexTimeoutId=null;var T=e.measureElementCache.has(e.options.getItemKey(l));if(T){var I=e.getOffsetForIndex(l,p),P=I[0];Ae(P,e.scrollOffset)||e.scrollToIndex(l,{align:p,behavior:u})}else e.scrollToIndex(l,{align:p,behavior:u})}))},this.scrollBy=function(l,n){var t=n===void 0?{}:n,i=t.behavior;e.cancelScrollToIndex(),i==="smooth"&&e.isDynamicMode()&&console.warn("The `smooth` scroll behavior is not fully supported with dynamic size."),e._scrollToOffset(e.scrollOffset+l,{adjustments:void 0,behavior:i})},this.getTotalSize=function(){var l;return(((l=e.getMeasurements()[e.options.count-1])==null?void 0:l.end)||e.options.paddingStart)-e.options.scrollMargin+e.options.paddingEnd},this._scrollToOffset=function(l,n){var t=n.adjustments,i=n.behavior;e.options.scrollToFn(l,{behavior:i,adjustments:t},e)},this.measure=function(){e.itemSizeCache=new Map,e.notify(!1)},this.setOptions(a),this.scrollRect=this.options.initialRect,this.scrollOffset=this.options.initialOffset,this.measurementsCache=this.options.initialMeasurementsCache,this.measurementsCache.forEach(function(l){e.itemSizeCache.set(l.key,l.size)}),this.maybeNotify()},fe=function(a,e,l,n){for(;a<=e;){var t=(a+e)/2|0,i=l(t);if(i<n)a=t+1;else if(i>n)e=t-1;else return t}return a>0?a-1:0};function Ke(o){for(var a=o.measurements,e=o.outerSize,l=o.scrollOffset,n=a.length-1,t=function(c){return a[c].start},i=fe(0,n,t,l),r=i;r<n&&a[r].end<l+e;)r++;return{startIndex:i,endIndex:r}}/**
 * vue-virtual
 *
 * Copyright (c) TanStack
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE.md file in the root directory of this source tree.
 *
 * @license MIT
 */function Ue(o){var a=new _e(Y(o)),e=Se(a),l=a._didMount();return N(function(){return Y(o).getScrollElement()},function(n){n&&a._willUpdate()},{immediate:!0}),N(function(){return Y(o)},function(n){a.setOptions(Q({},n,{onChange:function(i,r){ae(e),n.onChange==null||n.onChange(i,r)}})),a._willUpdate(),ae(e)},{immediate:!0}),ye(l),e}function qe(o){return Ue(S(function(){return Q({observeElementRect:ke,observeElementOffset:Be,scrollToFn:Le},Y(o))}))}function He(o,a,e){let l=D(e==null?void 0:e.value),n=S(()=>o.value!==void 0);return[S(()=>n.value?o.value:l.value),function(t){return n.value||(l.value=t),a==null?void 0:a(t)}]}function ue(o){return[o.screenX,o.screenY]}function We(){let o=D([-1,-1]);return{wasMoved(a){let e=ue(a);return o.value[0]===e[0]&&o.value[1]===e[1]?!1:(o.value=e,!0)},update(a){o.value=ue(a)}}}function Je({container:o,accept:a,walk:e,enabled:l}){W(()=>{let n=o.value;if(!n||l!==void 0&&!l.value)return;let t=ce(o);if(!t)return;let i=Object.assign(u=>a(u),{acceptNode:a}),r=t.createTreeWalker(n,NodeFilter.SHOW_ELEMENT,i,!1);for(;r.nextNode();)e(r.currentNode)})}function Ye(o){throw new Error("Unexpected object: "+o)}var z=(o=>(o[o.First=0]="First",o[o.Previous=1]="Previous",o[o.Next=2]="Next",o[o.Last=3]="Last",o[o.Specific=4]="Specific",o[o.Nothing=5]="Nothing",o))(z||{});function se(o,a){let e=a.resolveItems();if(e.length<=0)return null;let l=a.resolveActiveIndex(),n=l!=null?l:-1;switch(o.focus){case 0:{for(let t=0;t<e.length;++t)if(!a.resolveDisabled(e[t],t,e))return t;return l}case 1:{n===-1&&(n=e.length);for(let t=n-1;t>=0;--t)if(!a.resolveDisabled(e[t],t,e))return t;return l}case 2:{for(let t=n+1;t<e.length;++t)if(!a.resolveDisabled(e[t],t,e))return t;return l}case 3:{for(let t=e.length-1;t>=0;--t)if(!a.resolveDisabled(e[t],t,e))return t;return l}case 4:{for(let t=0;t<e.length;++t)if(a.resolveId(e[t],t,e)===o.id)return t;return l}case 5:return null;default:Ye(o)}}function pe(o={},a=null,e=[]){for(let[l,n]of Object.entries(o))be(e,me(a,l),n);return e}function me(o,a){return o?o+"["+a+"]":a}function be(o,a,e){if(Array.isArray(e))for(let[l,n]of e.entries())be(o,me(a,l.toString()),n);else e instanceof Date?o.push([a,e.toISOString()]):typeof e=="boolean"?o.push([a,e?"1":"0"]):typeof e=="string"?o.push([a,e]):typeof e=="number"?o.push([a,`${e}`]):e==null?o.push([a,""]):pe(e,a,o)}function Ze(o,a){return o===a}var Ge=(o=>(o[o.Open=0]="Open",o[o.Closed=1]="Closed",o))(Ge||{}),Xe=(o=>(o[o.Single=0]="Single",o[o.Multi=1]="Multi",o))(Xe||{}),Qe=(o=>(o[o.Pointer=0]="Pointer",o[o.Focus=1]="Focus",o[o.Other=2]="Other",o))(Qe||{});let he=Symbol("ComboboxContext");function _(o){let a=ve(he,null);if(a===null){let e=new Error(`<${o} /> is missing a parent <Combobox /> component.`);throw Error.captureStackTrace&&Error.captureStackTrace(e,_),e}return a}let ge=Symbol("VirtualContext"),et=L({name:"VirtualProvider",setup(o,{slots:a}){let e=_("VirtualProvider"),l=S(()=>{let r=O(e.optionsRef);if(!r)return{start:0,end:0};let u=window.getComputedStyle(r);return{start:parseFloat(u.paddingBlockStart||u.paddingTop),end:parseFloat(u.paddingBlockEnd||u.paddingBottom)}}),n=qe(S(()=>({scrollPaddingStart:l.value.start,scrollPaddingEnd:l.value.end,count:e.virtual.value.options.length,estimateSize(){return 40},getScrollElement(){return O(e.optionsRef)},overscan:12}))),t=S(()=>{var r;return(r=e.virtual.value)==null?void 0:r.options}),i=D(0);return N([t],()=>{i.value+=1}),de(ge,e.virtual.value?n:null),()=>[Z("div",{style:{position:"relative",width:"100%",height:`${n.value.getTotalSize()}px`},ref:r=>{if(r){if(typeof process<"u"&&{}.JEST_WORKER_ID!==void 0||e.activationTrigger.value===0)return;e.activeOptionIndex.value!==null&&e.virtual.value.options.length>e.activeOptionIndex.value&&n.value.scrollToIndex(e.activeOptionIndex.value)}}},n.value.getVirtualItems().map(r=>Oe(a.default({option:e.virtual.value.options[r.index],open:e.comboboxState.value===0})[0],{key:`${i.value}-${r.index}`,"data-index":r.index,"aria-setsize":e.virtual.value.options.length,"aria-posinset":r.index+1,style:{position:"absolute",top:0,left:0,transform:`translateY(${r.start}px)`,overflowAnchor:"none"}})))]}}),at=L({name:"Combobox",emits:{"update:modelValue":o=>!0},props:{as:{type:[Object,String],default:"template"},disabled:{type:[Boolean],default:!1},by:{type:[String,Function],nullable:!0,default:null},modelValue:{type:[Object,String,Number,Boolean],default:void 0},defaultValue:{type:[Object,String,Number,Boolean],default:void 0},form:{type:String,optional:!0},name:{type:String,optional:!0},nullable:{type:Boolean,default:!1},multiple:{type:[Boolean],default:!1},immediate:{type:[Boolean],default:!1},virtual:{type:Object,default:null}},inheritAttrs:!1,setup(o,{slots:a,attrs:e,emit:l}){let n=D(1),t=D(null),i=D(null),r=D(null),u=D(null),c=D({static:!1,hold:!1}),m=D([]),p=D(null),T=D(2),I=D(!1);function P(d=f=>f){let f=p.value!==null?m.value[p.value]:null,h=d(m.value.slice()),b=h.length>0&&h[0].dataRef.order.value!==null?h.sort((E,j)=>E.dataRef.order.value-j.dataRef.order.value):De(h,E=>O(E.dataRef.domRef)),C=f?b.indexOf(f):null;return C===-1&&(C=null),{options:b,activeOptionIndex:C}}let w=S(()=>o.multiple?1:0),A=S(()=>o.nullable),[x,s]=He(S(()=>o.modelValue),d=>l("update:modelValue",d),S(()=>o.defaultValue)),g=S(()=>x.value===void 0?U(w.value,{[1]:[],[0]:void 0}):x.value),y=null,M=null;function R(d){return U(w.value,{[0](){return s==null?void 0:s(d)},[1]:()=>{let f=k(v.value.value).slice(),h=k(d),b=f.findIndex(C=>v.compare(h,k(C)));return b===-1?f.push(h):f.splice(b,1),s==null?void 0:s(f)}})}let F=S(()=>{});N([F],([d],[f])=>{if(v.virtual.value&&d&&f&&p.value!==null){let h=d.indexOf(f[p.value]);h!==-1?p.value=h:p.value=null}});let v={comboboxState:n,value:g,mode:w,compare(d,f){if(typeof o.by=="string"){let h=o.by;return(d==null?void 0:d[h])===(f==null?void 0:f[h])}return o.by===null?Ze(d,f):o.by(d,f)},calculateIndex(d){return v.virtual.value?o.by===null?v.virtual.value.options.indexOf(d):v.virtual.value.options.findIndex(f=>v.compare(f,d)):m.value.findIndex(f=>v.compare(f.dataRef.value,d))},defaultValue:S(()=>o.defaultValue),nullable:A,immediate:S(()=>!1),virtual:S(()=>null),inputRef:i,labelRef:t,buttonRef:r,optionsRef:u,disabled:S(()=>o.disabled),options:m,change(d){s(d)},activeOptionIndex:S(()=>{if(I.value&&p.value===null&&(v.virtual.value?v.virtual.value.options.length>0:m.value.length>0)){if(v.virtual.value){let f=v.virtual.value.options.findIndex(h=>{var b;return!((b=v.virtual.value)!=null&&b.disabled(h))});if(f!==-1)return f}let d=m.value.findIndex(f=>!f.dataRef.disabled);if(d!==-1)return d}return p.value}),activationTrigger:T,optionsPropsRef:c,closeCombobox(){I.value=!1,!o.disabled&&n.value!==1&&(n.value=1,p.value=null)},openCombobox(){if(I.value=!0,!o.disabled&&n.value!==0){if(v.value.value){let d=v.calculateIndex(v.value.value);d!==-1&&(p.value=d)}n.value=0}},setActivationTrigger(d){T.value=d},goToOption(d,f,h){I.value=!1,y!==null&&cancelAnimationFrame(y),y=requestAnimationFrame(()=>{if(o.disabled||u.value&&!c.value.static&&n.value===1)return;if(v.virtual.value){p.value=d===z.Specific?f:se({focus:d},{resolveItems:()=>v.virtual.value.options,resolveActiveIndex:()=>{var E,j;return(j=(E=v.activeOptionIndex.value)!=null?E:v.virtual.value.options.findIndex(te=>{var H;return!((H=v.virtual.value)!=null&&H.disabled(te))}))!=null?j:null},resolveDisabled:E=>v.virtual.value.disabled(E),resolveId(){throw new Error("Function not implemented.")}}),T.value=h!=null?h:2;return}let b=P();if(b.activeOptionIndex===null){let E=b.options.findIndex(j=>!j.dataRef.disabled);E!==-1&&(b.activeOptionIndex=E)}let C=d===z.Specific?f:se({focus:d},{resolveItems:()=>b.options,resolveActiveIndex:()=>b.activeOptionIndex,resolveId:E=>E.id,resolveDisabled:E=>E.dataRef.disabled});p.value=C,T.value=h!=null?h:2,m.value=b.options})},selectOption(d){let f=m.value.find(b=>b.id===d);if(!f)return;let{dataRef:h}=f;R(h.value)},selectActiveOption(){if(v.activeOptionIndex.value!==null){if(v.virtual.value)R(v.virtual.value.options[v.activeOptionIndex.value]);else{let{dataRef:d}=m.value[v.activeOptionIndex.value];R(d.value)}v.goToOption(z.Specific,v.activeOptionIndex.value)}},registerOption(d,f){let h=Ie({id:d,dataRef:f});if(v.virtual.value){m.value.push(h);return}M&&cancelAnimationFrame(M);let b=P(C=>(C.push(h),C));p.value===null&&v.isSelected(f.value.value)&&(b.activeOptionIndex=b.options.indexOf(h)),m.value=b.options,p.value=b.activeOptionIndex,T.value=2,b.options.some(C=>!O(C.dataRef.domRef))&&(M=requestAnimationFrame(()=>{let C=P();m.value=C.options,p.value=C.activeOptionIndex}))},unregisterOption(d,f){if(y!==null&&cancelAnimationFrame(y),f&&(I.value=!0),v.virtual.value){m.value=m.value.filter(b=>b.id!==d);return}let h=P(b=>{let C=b.findIndex(E=>E.id===d);return C!==-1&&b.splice(C,1),b});m.value=h.options,p.value=h.activeOptionIndex,T.value=2},isSelected(d){return U(w.value,{[0]:()=>v.compare(k(v.value.value),k(d)),[1]:()=>k(v.value.value).some(f=>v.compare(k(f),k(d)))})},isActive(d){return p.value===v.calculateIndex(d)}};Me([i,r,u],()=>v.closeCombobox(),S(()=>n.value===0)),de(he,v),Ee(S(()=>U(n.value,{[0]:G.Open,[1]:G.Closed})));let V=S(()=>{var d;return(d=O(i))==null?void 0:d.closest("form")});return le(()=>{N([V],()=>{if(!V.value||o.defaultValue===void 0)return;function d(){v.change(o.defaultValue)}return V.value.addEventListener("reset",d),()=>{var f;(f=V.value)==null||f.removeEventListener("reset",d)}},{immediate:!0})}),()=>{var d,f,h;let{name:b,disabled:C,form:E,...j}=o,te={open:n.value===0,disabled:C,activeIndex:v.activeOptionIndex.value,activeOption:v.activeOptionIndex.value===null?null:v.virtual.value?v.virtual.value.options[(d=v.activeOptionIndex.value)!=null?d:0]:(h=(f=v.options.value[v.activeOptionIndex.value])==null?void 0:f.dataRef.value.value)!=null?h:null,value:g.value};return Z(Re,[...b!=null&&g.value!=null?pe({[b]:g.value}).map(([H,xe])=>Z(Pe,Te({features:ze.Hidden,key:H,as:"input",type:"hidden",hidden:!0,readOnly:!0,form:E,name:H,value:xe}))):[],q({theirProps:{...e,...oe(j,["by","defaultValue","immediate","modelValue","multiple","nullable","onUpdate:modelValue","virtual"])},ourProps:{},slot:te,slots:a,attrs:e,name:"Combobox"})])}}}),rt=L({name:"ComboboxLabel",props:{as:{type:[Object,String],default:"label"},id:{type:String,default:()=>`headlessui-combobox-label-${J()}`}},setup(o,{attrs:a,slots:e}){let l=_("ComboboxLabel");function n(){var t;(t=O(l.inputRef))==null||t.focus({preventScroll:!0})}return()=>{let t={open:l.comboboxState.value===0,disabled:l.disabled.value},{id:i,...r}=o,u={id:i,ref:l.labelRef,onClick:n};return q({ourProps:u,theirProps:r,slot:t,attrs:a,slots:e,name:"ComboboxLabel"})}}}),it=L({name:"ComboboxButton",props:{as:{type:[Object,String],default:"button"},id:{type:String,default:()=>`headlessui-combobox-button-${J()}`}},setup(o,{attrs:a,slots:e,expose:l}){let n=_("ComboboxButton");l({el:n.buttonRef,$el:n.buttonRef});function t(u){n.disabled.value||(n.comboboxState.value===0?n.closeCombobox():(u.preventDefault(),n.openCombobox()),B(()=>{var c;return(c=O(n.inputRef))==null?void 0:c.focus({preventScroll:!0})}))}function i(u){switch(u.key){case $.ArrowDown:u.preventDefault(),u.stopPropagation(),n.comboboxState.value===1&&n.openCombobox(),B(()=>{var c;return(c=n.inputRef.value)==null?void 0:c.focus({preventScroll:!0})});return;case $.ArrowUp:u.preventDefault(),u.stopPropagation(),n.comboboxState.value===1&&(n.openCombobox(),B(()=>{n.value.value||n.goToOption(z.Last)})),B(()=>{var c;return(c=n.inputRef.value)==null?void 0:c.focus({preventScroll:!0})});return;case $.Escape:if(n.comboboxState.value!==0)return;u.preventDefault(),n.optionsRef.value&&!n.optionsPropsRef.value.static&&u.stopPropagation(),n.closeCombobox(),B(()=>{var c;return(c=n.inputRef.value)==null?void 0:c.focus({preventScroll:!0})});return}}let r=$e(S(()=>({as:o.as,type:a.type})),n.buttonRef);return()=>{var u,c;let m={open:n.comboboxState.value===0,disabled:n.disabled.value,value:n.value.value},{id:p,...T}=o,I={ref:n.buttonRef,id:p,type:r.value,tabindex:"-1","aria-haspopup":"listbox","aria-controls":(u=O(n.optionsRef))==null?void 0:u.id,"aria-expanded":n.comboboxState.value===0,"aria-labelledby":n.labelRef.value?[(c=O(n.labelRef))==null?void 0:c.id,p].join(" "):void 0,disabled:n.disabled.value===!0?!0:void 0,onKeydown:i,onClick:t};return q({ourProps:I,theirProps:T,slot:m,attrs:a,slots:e,name:"ComboboxButton"})}}}),ut=L({name:"ComboboxInput",props:{as:{type:[Object,String],default:"input"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},displayValue:{type:Function},defaultValue:{type:String,default:void 0},id:{type:String,default:()=>`headlessui-combobox-input-${J()}`}},emits:{change:o=>!0},setup(o,{emit:a,attrs:e,slots:l,expose:n}){let t=_("ComboboxInput"),i=S(()=>ce(O(t.inputRef))),r={value:!1};n({el:t.inputRef,$el:t.inputRef});function u(){t.change(null);let s=O(t.optionsRef);s&&(s.scrollTop=0),t.goToOption(z.Nothing)}let c=S(()=>{var s;let g=t.value.value;return O(t.inputRef)?typeof o.displayValue<"u"&&g!==void 0?(s=o.displayValue(g))!=null?s:"":typeof g=="string"?g:"":""});le(()=>{N([c,t.comboboxState,i],([s,g],[y,M])=>{if(r.value)return;let R=O(t.inputRef);R&&((M===0&&g===1||s!==y)&&(R.value=s),requestAnimationFrame(()=>{var F;if(r.value||!R||((F=i.value)==null?void 0:F.activeElement)!==R)return;let{selectionStart:v,selectionEnd:V}=R;Math.abs((V!=null?V:0)-(v!=null?v:0))===0&&v===0&&R.setSelectionRange(R.value.length,R.value.length)}))},{immediate:!0}),N([t.comboboxState],([s],[g])=>{if(s===0&&g===1){if(r.value)return;let y=O(t.inputRef);if(!y)return;let M=y.value,{selectionStart:R,selectionEnd:F,selectionDirection:v}=y;y.value="",y.value=M,v!==null?y.setSelectionRange(R,F,v):y.setSelectionRange(R,F)}})});let m=D(!1);function p(){m.value=!0}function T(){re().nextFrame(()=>{m.value=!1})}function I(s){switch(r.value=!0,s.key){case $.Enter:if(r.value=!1,t.comboboxState.value!==0||m.value)return;if(s.preventDefault(),s.stopPropagation(),t.activeOptionIndex.value===null){t.closeCombobox();return}t.selectActiveOption(),t.mode.value===0&&t.closeCombobox();break;case $.ArrowDown:return r.value=!1,s.preventDefault(),s.stopPropagation(),U(t.comboboxState.value,{[0]:()=>t.goToOption(z.Next),[1]:()=>t.openCombobox()});case $.ArrowUp:return r.value=!1,s.preventDefault(),s.stopPropagation(),U(t.comboboxState.value,{[0]:()=>t.goToOption(z.Previous),[1]:()=>{t.openCombobox(),B(()=>{t.value.value||t.goToOption(z.Last)})}});case $.Home:if(s.shiftKey)break;return r.value=!1,s.preventDefault(),s.stopPropagation(),t.goToOption(z.First);case $.PageUp:return r.value=!1,s.preventDefault(),s.stopPropagation(),t.goToOption(z.First);case $.End:if(s.shiftKey)break;return r.value=!1,s.preventDefault(),s.stopPropagation(),t.goToOption(z.Last);case $.PageDown:return r.value=!1,s.preventDefault(),s.stopPropagation(),t.goToOption(z.Last);case $.Escape:if(r.value=!1,t.comboboxState.value!==0)return;s.preventDefault(),t.optionsRef.value&&!t.optionsPropsRef.value.static&&s.stopPropagation(),t.nullable.value&&t.mode.value===0&&t.value.value===null&&u(),t.closeCombobox();break;case $.Tab:if(r.value=!1,t.comboboxState.value!==0)return;t.mode.value===0&&t.activationTrigger.value!==1&&t.selectActiveOption(),t.closeCombobox();break}}function P(s){a("change",s),t.nullable.value&&t.mode.value===0&&s.target.value===""&&u(),t.openCombobox()}function w(s){var g,y,M;let R=(g=s.relatedTarget)!=null?g:ie.find(F=>F!==s.currentTarget);if(r.value=!1,!((y=O(t.optionsRef))!=null&&y.contains(R))&&!((M=O(t.buttonRef))!=null&&M.contains(R))&&t.comboboxState.value===0)return s.preventDefault(),t.mode.value===0&&(t.nullable.value&&t.value.value===null?u():t.activationTrigger.value!==1&&t.selectActiveOption()),t.closeCombobox()}function A(s){var g,y,M;let R=(g=s.relatedTarget)!=null?g:ie.find(F=>F!==s.currentTarget);(y=O(t.buttonRef))!=null&&y.contains(R)||(M=O(t.optionsRef))!=null&&M.contains(R)||t.disabled.value||t.immediate.value&&t.comboboxState.value!==0&&(t.openCombobox(),re().nextFrame(()=>{t.setActivationTrigger(1)}))}let x=S(()=>{var s,g,y,M;return(M=(y=(g=o.defaultValue)!=null?g:t.defaultValue.value!==void 0?(s=o.displayValue)==null?void 0:s.call(o,t.defaultValue.value):null)!=null?y:t.defaultValue.value)!=null?M:""});return()=>{var s,g,y,M,R,F,v;let V={open:t.comboboxState.value===0},{id:d,displayValue:f,onChange:h,...b}=o,C={"aria-controls":(s=t.optionsRef.value)==null?void 0:s.id,"aria-expanded":t.comboboxState.value===0,"aria-activedescendant":t.activeOptionIndex.value===null?void 0:t.virtual.value?(g=t.options.value.find(E=>!t.virtual.value.disabled(E.dataRef.value)&&t.compare(E.dataRef.value,t.virtual.value.options[t.activeOptionIndex.value])))==null?void 0:g.id:(y=t.options.value[t.activeOptionIndex.value])==null?void 0:y.id,"aria-labelledby":(F=(M=O(t.labelRef))==null?void 0:M.id)!=null?F:(R=O(t.buttonRef))==null?void 0:R.id,"aria-autocomplete":"list",id:d,onCompositionstart:p,onCompositionend:T,onKeydown:I,onInput:P,onFocus:A,onBlur:w,role:"combobox",type:(v=e.type)!=null?v:"text",tabIndex:0,ref:t.inputRef,defaultValue:x.value,disabled:t.disabled.value===!0?!0:void 0};return q({ourProps:C,theirProps:b,slot:V,attrs:e,slots:l,features:X.RenderStrategy|X.Static,name:"ComboboxInput"})}}}),st=L({name:"ComboboxOptions",props:{as:{type:[Object,String],default:"ul"},static:{type:Boolean,default:!1},unmount:{type:Boolean,default:!0},hold:{type:[Boolean],default:!1}},setup(o,{attrs:a,slots:e,expose:l}){let n=_("ComboboxOptions"),t=`headlessui-combobox-options-${J()}`;l({el:n.optionsRef,$el:n.optionsRef}),W(()=>{n.optionsPropsRef.value.static=o.static}),W(()=>{n.optionsPropsRef.value.hold=o.hold});let i=we(),r=S(()=>i!==null?(i.value&G.Open)===G.Open:n.comboboxState.value===0);return Je({container:S(()=>O(n.optionsRef)),enabled:S(()=>n.comboboxState.value===0),accept(u){return u.getAttribute("role")==="option"?NodeFilter.FILTER_REJECT:u.hasAttribute("role")?NodeFilter.FILTER_SKIP:NodeFilter.FILTER_ACCEPT},walk(u){u.setAttribute("role","none")}}),()=>{var u,c,m;let p={open:n.comboboxState.value===0},T={"aria-labelledby":(m=(u=O(n.labelRef))==null?void 0:u.id)!=null?m:(c=O(n.buttonRef))==null?void 0:c.id,id:t,ref:n.optionsRef,role:"listbox","aria-multiselectable":n.mode.value===1?!0:void 0},I=oe(o,["hold"]);return q({ourProps:T,theirProps:I,slot:p,attrs:a,slots:n.virtual.value&&n.comboboxState.value===0?{...e,default:()=>[Z(et,{},e.default)]}:e,features:X.RenderStrategy|X.Static,visible:r.value,name:"ComboboxOptions"})}}}),dt=L({name:"ComboboxOption",props:{as:{type:[Object,String],default:"li"},value:{type:[Object,String,Number,Boolean]},disabled:{type:Boolean,default:!1},order:{type:[Number],default:null}},setup(o,{slots:a,attrs:e,expose:l}){let n=_("ComboboxOption"),t=`headlessui-combobox-option-${J()}`,i=D(null);l({el:i,$el:i});let r=S(()=>{var x;return n.virtual.value?n.activeOptionIndex.value===n.calculateIndex(o.value):n.activeOptionIndex.value===null?!1:((x=n.options.value[n.activeOptionIndex.value])==null?void 0:x.id)===t}),u=S(()=>n.isSelected(o.value)),c=ve(ge,null),m=S(()=>({disabled:o.disabled,value:o.value,domRef:i,order:S(()=>o.order)}));le(()=>n.registerOption(t,m)),Ce(()=>n.unregisterOption(t,r.value)),W(()=>{let x=O(i);x&&(c==null||c.value.measureElement(x))}),W(()=>{n.comboboxState.value===0&&r.value&&(n.virtual.value||n.activationTrigger.value!==0&&B(()=>{var x,s;return(s=(x=O(i))==null?void 0:x.scrollIntoView)==null?void 0:s.call(x,{block:"nearest"})}))});function p(x){var s;if(o.disabled||(s=n.virtual.value)!=null&&s.disabled(o.value))return x.preventDefault();n.selectOption(t),Fe()||requestAnimationFrame(()=>{var g;return(g=O(n.inputRef))==null?void 0:g.focus({preventScroll:!0})}),n.mode.value===0&&requestAnimationFrame(()=>n.closeCombobox())}function T(){var x;if(o.disabled||(x=n.virtual.value)!=null&&x.disabled(o.value))return n.goToOption(z.Nothing);let s=n.calculateIndex(o.value);n.goToOption(z.Specific,s)}let I=We();function P(x){I.update(x)}function w(x){var s;if(!I.wasMoved(x)||o.disabled||(s=n.virtual.value)!=null&&s.disabled(o.value)||r.value)return;let g=n.calculateIndex(o.value);n.goToOption(z.Specific,g,0)}function A(x){var s;I.wasMoved(x)&&(o.disabled||(s=n.virtual.value)!=null&&s.disabled(o.value)||r.value&&(n.optionsPropsRef.value.hold||n.goToOption(z.Nothing)))}return()=>{let{disabled:x}=o,s={active:r.value,selected:u.value,disabled:x},g={id:t,ref:i,role:"option",tabIndex:x===!0?void 0:-1,"aria-disabled":x===!0?!0:void 0,"aria-selected":u.value,disabled:void 0,onClick:p,onFocus:T,onPointerenter:P,onMouseenter:P,onPointermove:w,onMousemove:w,onPointerleave:A,onMouseleave:A},y=oe(o,["order","value"]);return q({ourProps:g,theirProps:y,slot:s,attrs:e,slots:a,name:"ComboboxOption"})}}});export{at as Z,dt as a,rt as e,st as l,ut as o,it as t};
