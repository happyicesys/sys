import{o as r,f as c,b as t,a as o,w as l,d as n,n as a,u as d,i as m,t as s,c as h,J as f,F as g,k as v,m as b,H as k}from"./app.8dfb483a.js";function y(e,u){return r(),c("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5"})])}function w(e,u){return r(),c("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor","aria-hidden":"true"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5"})])}const j={class:"bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6"},B={class:"flex-1 flex justify-between sm:hidden"},C={class:"hidden sm:flex-1 sm:flex sm:items-center sm:justify-between"},N={class:"text-sm text-gray-700"},L={class:"font-medium"},P={class:"font-medium"},z={class:"font-medium"},D={class:"relative z-0 inline-flex rounded-md shadow-sm -space-x-px","aria-label":"Pagination"},S=t("span",{class:"sr-only"},"Previous",-1),V=t("span",{class:"sr-only"},"Next",-1),O={__name:"Paginator",props:{links:Object,meta:Object},setup(e){return(u,F)=>(r(),c("div",j,[t("div",B,[o(d(m),{href:e.links&&e.links.prev?e.links.prev:"#",class:a(["relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md",[!e.links&&e.links.prev?"opacity-25 cursor-not-allowed":"text-gray-700 bg-white hover:bg-gray-50"]]),disabled:!e.links.prev,"preserve-scroll":""},{default:l(()=>[n(" Previous ")]),_:1},8,["href","class","disabled"]),o(d(m),{href:e.links&&e.links.next?e.links.next:"#",class:a(["ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md",[!e.links&&e.links.next?"opacity-25 cursor-not-allowed":"text-gray-700 bg-white hover:bg-gray-50"]]),disabled:!e.links.next,"preserve-scroll":""},{default:l(()=>[n(" Next ")]),_:1},8,["href","class","disabled"])]),t("div",C,[t("div",null,[t("p",N,[n(" Showing "+s(" ")+" "),t("span",L,s(e.meta.from),1),n(" "+s(" ")+" to "+s(" ")+" "),t("span",P,s(e.meta.to),1),n(" "+s(" ")+" of "+s(" ")+" "),t("span",z,s(e.meta.total),1),n(" "+s(" ")+" results ")])]),t("div",null,[t("nav",D,[(r(),h(f(e.links&&e.links.prev?"Link":"span"),{href:e.links&&e.links.prev?e.links.prev:"#",class:a(["relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium",[e.links.prev?"text-gray-500 hover:bg-gray-50":"opacity-25 cursor-not-allowed"]]),disabled:!e.links.prev,"preserve-scroll":""},{default:l(()=>[S,o(d(y),{class:"h-5 w-5","aria-hidden":"true"})]),_:1},8,["href","class","disabled"])),(r(!0),c(g,null,v(e.meta.links,(i,x)=>b((r(),h(d(m),{href:i&&i.url?i.url:"#",class:a(["relative inline-flex items-center px-4 py-2 border text-sm font-medium",[i.active?"z-10 bg-indigo-50 border-indigo-500 text-indigo-600":"bg-white border-gray-300 text-gray-500 hover:bg-gray-50"]]),"preserve-scroll":""},{default:l(()=>[n(s(i.label),1)]),_:2},1032,["href","class"])),[[k,x!=0&&x!=e.meta.links.length-1]])),256)),(r(),h(f(e.links&&e.links.next?"Link":"span"),{href:e.links&&e.links.next?e.links.next:"#",class:a(["relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium",[e.links.next?"text-gray-500 hover:bg-gray-50":"opacity-25 cursor-not-allowed"]]),disabled:!e.links.next,"preserve-scroll":""},{default:l(()=>[V,o(d(w),{class:"h-5 w-5","aria-hidden":"true"})]),_:1},8,["href","class","disabled"]))])])])]))}};export{O as _};
