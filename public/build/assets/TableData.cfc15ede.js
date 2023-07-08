import{o as r,f as i,b as t,a as m,w as d,d as n,n as a,u as c,i as u,t as s,c as x,M as g,F as v,l as y,p as k,J as w,r as f}from"./app.1f3b9bf1.js";function p(e,l){return r(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5"})])}function C(e,l){return r(),i("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5"})])}const j={class:"bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6"},N={class:"flex-1 flex justify-between sm:hidden"},B={class:"hidden sm:flex-1 sm:flex sm:items-center sm:justify-between"},L={class:"text-sm text-gray-700"},S={class:"font-medium"},$={class:"font-medium"},z={class:"font-medium"},D={class:"relative z-0 inline-flex rounded-md shadow-sm -space-x-px","aria-label":"Pagination"},P=t("span",{class:"sr-only"},"Previous",-1),M=t("span",{class:"sr-only"},"Next",-1),V={__name:"Paginator",props:{links:Object,meta:Object},setup(e){return(l,h)=>(r(),i("div",j,[t("div",N,[m(c(u),{href:e.links&&e.links.prev?e.links.prev:"#",class:a(["relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md",[!e.links&&e.links.prev?"opacity-25 cursor-not-allowed":"text-gray-700 bg-white hover:bg-gray-50"]]),disabled:!e.links.prev,"preserve-scroll":""},{default:d(()=>[n(" Previous ")]),_:1},8,["href","class","disabled"]),m(c(u),{href:e.links&&e.links.next?e.links.next:"#",class:a(["ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md",[!e.links&&e.links.next?"opacity-25 cursor-not-allowed":"text-gray-700 bg-white hover:bg-gray-50"]]),disabled:!e.links.next,"preserve-scroll":""},{default:d(()=>[n(" Next ")]),_:1},8,["href","class","disabled"])]),t("div",B,[t("div",null,[t("p",L,[n(" Showing "+s(" ")+" "),t("span",S,s(e.meta.from),1),n(" "+s(" ")+" to "+s(" ")+" "),t("span",$,s(e.meta.to),1),n(" "+s(" ")+" of "+s(" ")+" "),t("span",z,s(e.meta.total),1),n(" "+s(" ")+" results ")])]),t("div",null,[t("nav",D,[(r(),x(g(e.links&&e.links.prev?"Link":"span"),{href:e.links&&e.links.prev?e.links.prev:"#",class:a(["relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium",[e.links.prev?"text-gray-500 hover:bg-gray-50":"opacity-25 cursor-not-allowed"]]),disabled:!e.links.prev,"preserve-scroll":""},{default:d(()=>[P,m(c(p),{class:"h-5 w-5","aria-hidden":"true"})]),_:1},8,["href","class","disabled"])),(r(!0),i(v,null,y(e.meta.links,(o,b)=>k((r(),x(c(u),{href:o&&o.url?o.url:"#",class:a(["relative inline-flex items-center px-4 py-2 border text-sm font-medium",[o.active?"z-10 bg-indigo-50 border-indigo-500 text-indigo-600":"bg-white border-gray-300 text-gray-500 hover:bg-gray-50"]]),"preserve-scroll":""},{default:d(()=>[n(s(o.label),1)]),_:2},1032,["href","class"])),[[w,b!=0&&b!=e.meta.links.length-1]])),256)),(r(),x(g(e.links&&e.links.next?"Link":"span"),{href:e.links&&e.links.next?e.links.next:"#",class:a(["relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium",[e.links.next?"text-gray-500 hover:bg-gray-50":"opacity-25 cursor-not-allowed"]]),disabled:!e.links.next,"preserve-scroll":""},{default:d(()=>[M,m(c(C),{class:"h-5 w-5","aria-hidden":"true"})]),_:1},8,["href","class","disabled"]))])])])]))}},F={__name:"TableHead",props:{inputClass:String},setup(e){return(l,h)=>(r(),i("th",{scope:"col",class:a(["sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-[13px] font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-2 lg:pl-2",e.inputClass])},[f(l.$slots,"default")],2))}},O={__name:"TableData",props:{inputClass:String,currentIndex:Number,totalLength:Number},setup(e){return(l,h)=>(r(),i("td",{class:a([e.currentIndex!==e.totalLength-1?"border-b border-gray-200":"","whitespace-normal py-2 pl-1 pr-1 text-[13px] font-medium text-gray-800 sm:pl-1 lg:pl-1",e.inputClass])},[f(l.$slots,"default")],2))}};export{F as _,V as a,O as b};
