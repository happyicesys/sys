import{o,f as s,b as t,r as a,l as n}from"./app.242e5fba.js";const d={scope:"col",class:"sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-1 pr-1 text-center text-[13px] font-semibold text-gray-900 backdrop-blur backdrop-filter"},i={class:"flex justify-center"},c={class:"pt-0.5 text-blue-600 hover:text-blue-800"},h={key:0},u=t("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M19 9l-7 7-7-7"})],-1),m=[u],b={key:1},k=t("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M5 15l7-7 7 7"})],-1),y=[k],p={__name:"TableHeadSort",props:{modelName:String,sortKey:String,sortBy:{type:Boolean,default:!0}},setup(e){return(r,l)=>(o(),s("th",d,[t("div",i,[t("a",{href:"#",class:"text-blue-600 hover:text-blue-800",onClick:l[0]||(l[0]=w=>r.$emit("sortTable",e.modelName))},[a(r.$slots,"default")]),t("div",c,[e.sortKey===e.modelName&&e.sortBy?(o(),s("span",h,m)):n("",!0),e.sortKey===e.modelName&&!e.sortBy?(o(),s("span",b,y)):n("",!0)])])]))}};export{p as _};
