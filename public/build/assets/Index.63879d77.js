import{_ as K}from"./Authenticated.5ddcd631.js";import{_ as c}from"./Button.8f07713f.js";import T from"./Form.eb1f5e77.js";import{r as D,a as A,T as M,_ as E,b as H,c as b}from"./TableHeadSort.791fcb96.js";import{_ as U}from"./SearchInput.67bbe6d1.js";import{_ as O}from"./MultiSelect.3dcab989.js";import{i as u,j as R,o as d,g as h,a as t,b as i,w as a,F as P,H as J,d as e,t as f,m as q,p as w,c as B,f as p,J as k}from"./app.8d2b0b7b.js";import{r as z,a as G}from"./PlusIcon.1602df3e.js";import{r as Q}from"./TrashIcon.e460d703.js";import"./open-closed.0e9108b0.js";import"./use-resolve-button-type.d0db1549.js";import"./RectangleStackIcon.68c664a2.js";import"./FormInput.0d66dffe.js";import"./Modal.bd162e03.js";import"./ArrowUturnLeftIcon.937eb6d4.js";import"./_plugin-vue_export-helper.cdc0426e.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Payment Methods) ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=p(" Name "),ae={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},oe={class:"mt-3"},ne={class:"flex space-x-1"},le=e("span",null," Search ",-1),re=e("span",null," Reset ",-1),de={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},me=e("span",null,"Showing",-1),ce={class:"font-medium"},ue=e("span",null,"to",-1),fe={class:"font-medium"},pe=e("span",null,"of",-1),ge={class:"font-medium"},he=e("span",null,"results",-1),_e={class:"mt-6 flex flex-col"},xe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ye={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},ke=p(" # "),Ce=p(" Name "),$e={class:"bg-white"},Me={class:"flex justify-center space-x-1"},Pe=e("span",null," Edit ",-1),Be=e("span",null," Delete ",-1),Se={key:0},Ve=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ne=[Ve],Ge={__name:"Index",props:{paymentMethods:Object},setup(o){const n=u({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),m=u(!1),_=u(),x=u(""),y=u([]);R(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.numberPerPage=y.value[0]});function S(){x.value="create",_.value=null,m.value=!0}function V(r){!confirm("Are you sure to delete "+r.name+"?")||k.Inertia.delete("/payment-methods/"+r.id)}function N(r){x.value="update",_.value=r,m.value=!0}function v(){k.Inertia.get("/payment-methods",{...n.value,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){k.Inertia.get("/payment-methods")}function L(r){n.value.sortKey=r,n.value.sortBy=!n.value.sortBy,v()}function j(){m.value=!1}return(r,s)=>(d(),h(P,null,[t(i(J),{title:"Payment Methods"}),t(K,null,{header:a(()=>[W]),default:a(()=>{var C,$;return[e("div",X,[e("div",Y,[e("div",Z,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>S())},{default:a(()=>[t(i(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(U,{placeholderStr:"Name",modelValue:n.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>n.value.name=l)},{default:a(()=>[se]),_:1},8,["modelValue"])]),e("div",ae,[e("div",oe,[e("div",ne,[t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>v())},{default:a(()=>[t(i(D),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(c,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>I())},{default:a(()=>[t(i(A),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})])]),e("div",de,[e("p",ie,[me,e("span",ce,f((C=o.paymentMethods.meta.from)!=null?C:0),1),ue,e("span",fe,f(($=o.paymentMethods.meta.to)!=null?$:0),1),pe,e("span",ge,f(o.paymentMethods.meta.total),1),he]),t(O,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>n.value.numberPerPage=l),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:v},null,8,["modelValue","options"])])])]),e("div",_e,[e("div",xe,[e("div",ye,[e("table",ve,[e("thead",be,[e("tr",we,[t(M,null,{default:a(()=>[ke]),_:1}),t(E,{modelName:"name",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:s[5]||(s[5]=l=>L("name"))},{default:a(()=>[Ce]),_:1},8,["sortKey","sortBy"]),t(M)])]),e("tbody",$e,[(d(!0),h(P,null,q(o.paymentMethods.data,(l,g)=>(d(),h("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:g,totalLength:o.paymentMethods.length,inputClass:"text-center"},{default:a(()=>[p(f(o.paymentMethods.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:o.paymentMethods.length,inputClass:"text-left"},{default:a(()=>[p(f(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:o.paymentMethods.length,inputClass:"text-center"},{default:a(()=>[e("div",Me,[t(c,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>N(l)},{default:a(()=>[t(i(G),{class:"w-4 h-4"}),Pe]),_:2},1032,["onClick"]),t(c,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>V(l)},{default:a(()=>[t(i(Q),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.paymentMethods.data.length?w("",!0):(d(),h("tr",Se,Ne))])]),o.paymentMethods.data.length?(d(),B(H,{key:0,links:o.paymentMethods.links,meta:o.paymentMethods.meta},null,8,["links","meta"])):w("",!0)])])])]),m.value?(d(),B(T,{key:0,paymentMethod:_.value,type:x.value,showModal:m.value,onModalClose:j},null,8,["paymentMethod","type","showModal"])):w("",!0)]}),_:1})],64))}};export{Ge as default};
