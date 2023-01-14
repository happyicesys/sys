import{_ as K}from"./Authenticated.2894f92e.js";import{_ as u}from"./Button.1d76c393.js";import T from"./Form.74aaaa9e.js";import{_ as D,r as A,T as $,a as E,b as H,c as b}from"./TableHeadSort.8ecdda5e.js";import{_ as U}from"./MultiSelect.b58d4ceb.js";import{i as m,j as O,o as i,g as h,a as t,b as d,w as o,F as B,H as R,d as e,t as f,m as J,p as P,c as S,f as g,J as w}from"./app.ad0996d0.js";import{r as q}from"./PlusIcon.e516c08f.js";import{r as z}from"./BackspaceIcon.321b30ba.js";import{r as G}from"./PencilSquareIcon.b8a08839.js";import{r as Q}from"./TrashIcon.f9065a8e.js";import"./open-closed.4c312123.js";import"./use-resolve-button-type.fe156099.js";import"./RectangleStackIcon.c7c7181f.js";import"./FormInput.89a1716a.js";import"./Modal.333b53d2.js";import"./ArrowUturnLeftIcon.92b36951.js";import"./CheckCircleIcon.9ddca09e.js";import"./_plugin-vue_export-helper.cdc0426e.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Cashless Provider) ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=g(" Name "),oe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ae={class:"mt-3"},ne={class:"flex space-x-1"},le=e("span",null," Search ",-1),re=e("span",null," Reset ",-1),ie={class:"flex flex-col space-y-2"},de={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),ue={class:"font-medium"},me=e("span",null,"to",-1),fe={class:"font-medium"},ge=e("span",null,"of",-1),pe={class:"font-medium"},he=e("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},xe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},_e={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},we=g(" # "),ke=g(" Name "),Ce={class:"bg-white"},$e={class:"flex justify-center space-x-1"},Be=e("span",null," Edit ",-1),Se=e("span",null," Delete ",-1),Ve={key:0},Ne=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ie=[Ne],We={__name:"Index",props:{cashlessProviders:Object},setup(a){const n=m({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),c=m(!1),v=m(),x=m(""),_=m([]);O(()=>{_.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.numberPerPage=_.value[0]});function V(){x.value="create",v.value=null,c.value=!0}function N(r){!confirm("Are you sure to delete "+r.name+"?")||w.Inertia.delete("/cashless-providers/"+r.id)}function I(r){x.value="update",v.value=r,c.value=!0}function y(){w.Inertia.get("/cashless-providers",{...n.value,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function L(){w.Inertia.get("/cashless-providers")}function M(r){n.value.sortKey=r,n.value.sortBy=!n.value.sortBy,y()}function j(){c.value=!1}return(r,s)=>(i(),h(B,null,[t(d(R),{title:"Cashless Provider"}),t(K,null,{header:o(()=>[W]),default:o(()=>{var k,C;return[e("div",X,[e("div",Y,[e("div",Z,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>V())},{default:o(()=>[t(d(q),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(D,{placeholderStr:"Name",modelValue:n.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>n.value.name=l)},{default:o(()=>[se]),_:1},8,["modelValue"])]),e("div",oe,[e("div",ae,[e("div",ne,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>y())},{default:o(()=>[t(d(A),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>L())},{default:o(()=>[t(d(z),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})])]),e("div",ie,[e("p",de,[ce,e("span",ue,f((k=a.cashlessProviders.meta.from)!=null?k:0),1),me,e("span",fe,f((C=a.cashlessProviders.meta.to)!=null?C:0),1),ge,e("span",pe,f(a.cashlessProviders.meta.total),1),he]),t(U,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>n.value.numberPerPage=l),options:_.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",ve,[e("div",xe,[e("div",_e,[e("table",ye,[e("thead",be,[e("tr",Pe,[t($,null,{default:o(()=>[we]),_:1}),t(E,{modelName:"name",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[5]||(s[5]=l=>M("name"))},{default:o(()=>[ke]),_:1},8,["sortKey","sortBy"]),t($)])]),e("tbody",Ce,[(i(!0),h(B,null,J(a.cashlessProviders.data,(l,p)=>(i(),h("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:p,totalLength:a.cashlessProviders.length,inputClass:"text-center"},{default:o(()=>[g(f(a.cashlessProviders.meta.from+p),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:a.cashlessProviders.length,inputClass:"text-left"},{default:o(()=>[g(f(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:p,totalLength:a.cashlessProviders.length,inputClass:"text-center"},{default:o(()=>[e("div",$e,[t(u,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>I(l)},{default:o(()=>[t(d(G),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"]),t(u,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>N(l)},{default:o(()=>[t(d(Q),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.cashlessProviders.data.length?P("",!0):(i(),h("tr",Ve,Ie))])]),a.cashlessProviders.data.length?(i(),S(H,{key:0,links:a.cashlessProviders.links,meta:a.cashlessProviders.meta},null,8,["links","meta"])):P("",!0)])])])]),c.value?(i(),S(T,{key:0,cashlessProvider:v.value,type:x.value,showModal:c.value,onModalClose:j},null,8,["cashlessProvider","type","showModal"])):P("",!0)]}),_:1})],64))}};export{We as default};
