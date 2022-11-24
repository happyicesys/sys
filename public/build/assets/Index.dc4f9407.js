import{_ as U}from"./Authenticated.74d7a764.js";import{_ as g}from"./Button.3a6c59a3.js";import K from"./Form.ebd9ed24.js";import{r as T,a as A,T as w,_ as D,b as H,c as _}from"./TableHeadSort.2f7fb876.js";import{_ as V}from"./SearchInput.3a6fa154.js";import{_ as O}from"./MultiSelect.0db47f49.js";import{i as p,j as R,o as d,g as x,a as t,b as u,w as s,F as B,H as J,d as e,t as c,m as q,p as k,c as S,f as r,J as C}from"./app.3d3fc13a.js";import{r as z,a as G}from"./PlusIcon.39a324fb.js";import{r as Q}from"./TrashIcon.607efdf7.js";import"./open-closed.8d3f1b99.js";import"./use-resolve-button-type.41fc38d7.js";import"./RectangleStackIcon.db147885.js";import"./Modal.bca60b9f.js";import"./ArrowUturnLeftIcon.2bf01653.js";import"./_plugin-vue_export-helper.cdc0426e.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Users ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=r(" Name "),ae=r(" Email "),oe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},le={class:"flex space-x-1"},re=e("span",null," Search ",-1),ie=e("span",null," Reset ",-1),de={class:"flex flex-col space-y-2"},ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),me={class:"font-medium"},fe=e("span",null,"to",-1),ge={class:"font-medium"},pe=e("span",null,"of",-1),_e={class:"font-medium"},xe=e("span",null,"results",-1),he={class:"mt-6 flex flex-col"},ve={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},we={class:"bg-gray-100"},ke={class:"divide-x divide-gray-200"},Ce=r(" # "),$e=r(" Name "),Pe=r(" Email "),Ve={class:"bg-white"},Be={class:"flex justify-center space-x-1"},Se=e("span",null," Edit ",-1),Le=e("span",null," Delete ",-1),Ne={key:0},Ie=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),je=[Ie],Qe={__name:"Index",props:{users:Object,countries:Object},setup(l){const n=p({name:"",uen:"",sortKey:"",sortBy:!0,numberPerPage:100}),m=p(!1),h=p(),v=p(""),y=p([]);R(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.numberPerPage=y.value[0]});function L(){v.value="create",h.value=null,m.value=!0}function N(i){!confirm("Are you sure to delete "+i.name+"?")||C.Inertia.delete("/users/"+i.id)}function I(i){v.value="update",h.value=i,m.value=!0}function b(){C.Inertia.get("/users",{...n.value,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(){C.Inertia.get("/users")}function E(i){n.value.sortKey=i,n.value.sortBy=!n.value.sortBy,b()}function F(){m.value=!1}return(i,a)=>(d(),x(B,null,[t(u(J),{title:"Users"}),t(U,null,{header:s(()=>[W]),default:s(()=>{var $,P;return[e("div",X,[e("div",Y,[e("div",Z,[t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[0]||(a[0]=o=>L())},{default:s(()=>[t(u(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(V,{placeholderStr:"Name",modelValue:n.value.name,"onUpdate:modelValue":a[1]||(a[1]=o=>n.value.name=o)},{default:s(()=>[se]),_:1},8,["modelValue"]),t(V,{placeholderStr:"Email",modelValue:n.value.email,"onUpdate:modelValue":a[2]||(a[2]=o=>n.value.email=o)},{default:s(()=>[ae]),_:1},8,["modelValue"])]),e("div",oe,[e("div",ne,[e("div",le,[t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[3]||(a[3]=o=>b())},{default:s(()=>[t(u(T),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1}),t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[4]||(a[4]=o=>j())},{default:s(()=>[t(u(A),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1})])]),e("div",de,[e("p",ue,[ce,e("span",me,c(($=l.users.meta.from)!=null?$:0),1),fe,e("span",ge,c((P=l.users.meta.to)!=null?P:0),1),pe,e("span",_e,c(l.users.meta.total),1),xe]),t(O,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":a[5]||(a[5]=o=>n.value.numberPerPage=o),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",he,[e("div",ve,[e("div",ye,[e("table",be,[e("thead",we,[e("tr",ke,[t(w,null,{default:s(()=>[Ce]),_:1}),t(D,{modelName:"name",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:a[6]||(a[6]=o=>E("name"))},{default:s(()=>[$e]),_:1},8,["sortKey","sortBy"]),t(w,null,{default:s(()=>[Pe]),_:1}),t(w)])]),e("tbody",Ve,[(d(!0),x(B,null,q(l.users.data,(o,f)=>(d(),x("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(_,{currentIndex:f,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[r(c(l.users.meta.from+f),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:f,totalLength:l.users.length,inputClass:"text-left"},{default:s(()=>[r(c(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:f,totalLength:l.users.length,inputClass:"text-left"},{default:s(()=>[r(c(o.email),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:f,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[e("div",Be,[t(g,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:M=>I(o)},{default:s(()=>[t(u(G),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"]),t(g,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:M=>N(o)},{default:s(()=>[t(u(Q),{class:"w-4 h-4"}),Le]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.users.data.length?k("",!0):(d(),x("tr",Ne,je))])]),l.users.data.length?(d(),S(H,{key:0,links:l.users.links,meta:l.users.meta},null,8,["links","meta"])):k("",!0)])])])]),m.value?(d(),S(K,{key:0,user:h.value,type:v.value,showModal:m.value,onModalClose:F},null,8,["user","type","showModal"])):k("",!0)]}),_:1})],64))}};export{Qe as default};