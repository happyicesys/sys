import{o,f as s,b as t,r as a,m as l}from"./app.a07993d3.js";const i={scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-1 pr-1 text-center text-[13px] font-semibold text-gray-900 backdrop-blur backdrop-filter"},d={class:"flex justify-center"},c={class:"pt-0.5 text-blue-600 hover:text-blue-800"},h={key:0},m=t("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M19 9l-7 7-7-7"})],-1),u=[m],b={key:1},k=t("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M5 15l7-7 7 7"})],-1),y=[k],x={__name:"TableHeadSort",props:{modelName:String,sortKey:String,sortBy:Boolean},setup(e){return(r,n)=>(o(),s("th",i,[t("div",d,[t("a",{href:"#",class:"text-blue-600 hover:text-blue-800",onClick:n[0]||(n[0]=w=>r.$emit("sortTable",e.modelName))},[a(r.$slots,"default")]),t("div",c,[e.sortKey===e.modelName&&e.sortBy?(o(),s("span",h,u)):l("",!0),e.sortKey===e.modelName&&!e.sortBy?(o(),s("span",b,y)):l("",!0)])])]))}};export{x as _};