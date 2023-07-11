import{_ as M}from"./Authenticated.b0da2f61.js";import{_ as x}from"./Button.bcd120db.js";import T from"./Form.2de89dfe.js";import{_ as h,a as A,b as f}from"./TableData.d81f6676.js";import{_ as S}from"./SearchInput.cac6d3ec.js";import{_ as D}from"./MultiSelect.ceaaecb2.js";import{_ as N}from"./TableHeadSort.0fa775ac.js";import{h as v,K as R,j as Z,f as b,a as t,u as i,w as s,F as K,o as d,Z as q,b as e,c as _,m as g,d as r,t as u,l as z,O as B}from"./app.2ca23373.js";import{r as G}from"./PlusIcon.767dcd0f.js";import{r as H}from"./MagnifyingGlassIcon.5043c312.js";import{r as J}from"./BackspaceIcon.6ada51e5.js";import{r as Q}from"./PencilSquareIcon.c1b24ff0.js";import{r as W}from"./TrashIcon.5dbdb0a3.js";import"./open-closed.2ea361b8.js";import"./use-resolve-button-type.57a6ffc5.js";import"./RectangleStackIcon.8ba474a2.js";import"./FormInput.b781b8bc.js";import"./Modal.e7583ca9.js";import"./ArrowUturnLeftIcon.bc0b4728.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.95dad68a.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Users ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},le={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},ae={class:"flex space-x-1"},re=e("span",null," Search ",-1),ie=e("span",null," Reset ",-1),de={class:"flex flex-col space-y-2"},ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},me=e("span",null,"Showing",-1),ce={class:"font-medium"},fe=e("span",null,"to",-1),ge={class:"font-medium"},pe=e("span",null,"of",-1),xe={class:"font-medium"},he=e("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},_e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ke={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},Ce={class:"bg-white"},$e={class:"flex justify-center space-x-1"},Be=e("span",null," Edit ",-1),Le=e("span",null," Delete ",-1),Pe={key:0},Ve=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Ve],We={__name:"Index",props:{users:Object,countries:Object,operators:Object,roles:Object},setup(l){const a=v({name:"",uen:"",sortKey:"",sortBy:!0,numberPerPage:100}),p=v(!1),k=v(),w=v(""),C=v([]),y=R().props.auth.permissions;Z(()=>{C.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=C.value[0]});function j(){w.value="create",k.value=null,p.value=!0}function I(c){!confirm("Are you sure to delete "+c.name+"?")||B.delete("/users/"+c.id)}function O(c){w.value="update",k.value=c,p.value=!0}function $(){B.get("/users",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function U(){B.get("/users")}function L(c){a.value.sortKey=c,a.value.sortBy=!a.value.sortBy,$()}function E(){p.value=!1}return(c,n)=>(d(),b(K,null,[t(i(q),{title:"Users"}),t(M,null,{header:s(()=>[X]),default:s(()=>{var P,V;return[e("div",Y,[e("div",ee,[e("div",te,[i(y).includes("create users")?(d(),_(x,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[0]||(n[0]=o=>j())},{default:s(()=>[t(i(G),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})):g("",!0)]),e("div",oe,[t(S,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":n[1]||(n[1]=o=>a.value.name=o)},{default:s(()=>[r(" Name ")]),_:1},8,["modelValue"]),t(S,{placeholderStr:"Email",modelValue:a.value.email,"onUpdate:modelValue":n[2]||(n[2]=o=>a.value.email=o)},{default:s(()=>[r(" Email ")]),_:1},8,["modelValue"])]),e("div",le,[e("div",ne,[e("div",ae,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[3]||(n[3]=o=>$())},{default:s(()=>[t(i(H),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1}),t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[4]||(n[4]=o=>U())},{default:s(()=>[t(i(J),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1})])]),e("div",de,[e("p",ue,[me,e("span",ce,u((P=l.users.meta.from)!=null?P:0),1),fe,e("span",ge,u((V=l.users.meta.to)!=null?V:0),1),pe,e("span",xe,u(l.users.meta.total),1),he]),t(D,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":n[5]||(n[5]=o=>a.value.numberPerPage=o),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:$},null,8,["modelValue","options"])])])]),e("div",ve,[e("div",_e,[e("div",ye,[e("table",be,[e("thead",ke,[e("tr",we,[t(h,null,{default:s(()=>[r(" # ")]),_:1}),t(N,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:n[6]||(n[6]=o=>L("name"))},{default:s(()=>[r(" Name ")]),_:1},8,["sortKey","sortBy"]),t(h,null,{default:s(()=>[r(" Email ")]),_:1}),t(h,null,{default:s(()=>[r(" Username ")]),_:1}),t(h,null,{default:s(()=>[r(" Role ")]),_:1}),t(N,{modelName:"operator_id",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:n[7]||(n[7]=o=>L("operator_id"))},{default:s(()=>[r(" Belongs to Operator ")]),_:1},8,["sortKey","sortBy"]),t(h)])]),e("tbody",Ce,[(d(!0),b(K,null,z(l.users.data,(o,m)=>(d(),b("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(f,{currentIndex:m,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[r(u(l.users.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:m,totalLength:l.users.length,inputClass:"text-left"},{default:s(()=>[r(u(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:m,totalLength:l.users.length,inputClass:"text-left"},{default:s(()=>[r(u(o.email),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:m,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[r(u(o.username),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:m,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[r(u(o.roles[0]?o.roles[0].name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:m,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[r(u(o.operator.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(f,{currentIndex:m,totalLength:l.users.length,inputClass:"text-center"},{default:s(()=>[e("div",$e,[i(y).includes("update users")?(d(),_(x,{key:0,type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>O(o)},{default:s(()=>[t(i(Q),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"])):g("",!0),i(y).includes("delete users")?(d(),_(x,{key:1,type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>I(o)},{default:s(()=>[t(i(W),{class:"w-4 h-4"}),Le]),_:2},1032,["onClick"])):g("",!0)])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.users.data.length?g("",!0):(d(),b("tr",Pe,Se))])]),l.users.data.length?(d(),_(A,{key:0,links:l.users.links,meta:l.users.meta},null,8,["links","meta"])):g("",!0)])])])]),p.value?(d(),_(T,{key:0,user:k.value,operators:l.operators,roles:l.roles,type:w.value,showModal:p.value,permissions:i(y),onModalClose:E},null,8,["user","operators","roles","type","showModal","permissions"])):g("",!0)]}),_:1})],64))}};export{We as default};
